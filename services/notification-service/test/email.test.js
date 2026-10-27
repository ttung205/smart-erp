import { createHmac, randomUUID } from 'node:crypto';
import assert from 'node:assert/strict';
import { once } from 'node:events';
import { createServer } from 'node:http';
import { after, before, test } from 'node:test';
import { createApp } from '../src/app.js';
import { createAttachmentFetcher } from '../src/email/attachment.fetcher.js';
import { EmailEventHandler } from '../src/email/email.events.js';
import { markEmailFailed, sendQueuedEmail } from '../src/email/email.routes.js';
import { NotificationRepository } from '../src/notifications/notification.repository.js';
import { NotificationService } from '../src/notifications/notification.service.js';
import { openTestDatabase } from './helpers/mongo.js';

const database = await openTestDatabase();
const SECRET = 'email-test-secret';
const INTERNAL_KEY = 'email-test-internal-key';
const silent = { log() {}, warn() {}, error() {} };
const server = createServer(createApp({ database, repository: {}, jwtSecret: SECRET, internalServiceKey: INTERNAL_KEY }));
let baseUrl;

function token(permissions, { secret = SECRET, exp = Math.floor(Date.now() / 1000) + 3600, iss = 'identity-service' } = {}) {
  const encode = (value) => Buffer.from(JSON.stringify(value)).toString('base64url');
  const header = encode({ typ: 'JWT', alg: 'HS256' });
  const payload = encode({ iss, sub: '7', name: 'User 7', permissions, exp });
  return `${header}.${payload}.${createHmac('sha256', secret).update(`${header}.${payload}`).digest('base64url')}`;
}
const bearer = (permissions, options) => ({ Authorization: `Bearer ${token(permissions, options)}` });
const api = (path, headers = {}, init = {}) => fetch(`${baseUrl}/api/v1/notifications/email${path}`, { headers, ...init });

before(async () => {
  server.listen(0);
  await once(server, 'listening');
  baseUrl = `http://127.0.0.1:${server.address().port}`;
});
after(async () => { server.close(); await database.drop(); await database.close(); });

/** Event *EmailRequested như Sales / Purchase phát ra qua outbox. */
function emailEvent(overrides = {}, eventType = 'QuotationEmailRequested') {
  const { email, attachments, ...rest } = overrides;
  return {
    event_id: randomUUID(),
    event_type: eventType,
    aggregate_type: 'Quotation',
    aggregate_id: '10',
    payload: {
      email: { job_id: randomUUID(), to: ['khach@example.com'], cc: ['sep@example.com'], subject: 'Báo giá QT-001', body: 'Kính gửi,\nXin gửi báo giá.', sender_name: 'Công ty ABC', ...email },
      attachments: attachments ?? [{ source: 'sales', id: 5, filename: 'QT-001.pdf', content_type: 'application/pdf' }],
      ...rest,
    },
  };
}

function buildService() {
  const enqueued = [];
  const emailEvents = new EmailEventHandler({ database, enqueue: async (job) => enqueued.push(job), fromAddress: 'sales@thanhtung.vn', logger: silent });
  const repository = new NotificationRepository(database);
  const service = new NotificationService({ repository, resolver: {}, rules: {}, publisher: {}, emailEvents, logger: silent });
  return { service, enqueued };
}

test('email event is turned into a QUEUED job; the attachment stays a reference and no notification is created', async () => {
  const { service, enqueued } = buildService();
  const event = emailEvent();

  const result = await service.handleEvent(event);

  assert.deepEqual(result, { emailQueued: event.payload.email.job_id });
  assert.equal(enqueued.length, 1);
  const [job] = enqueued;
  assert.equal(job.id, event.payload.email.job_id);
  assert.equal(job.mailOptions.to, 'khach@example.com');
  assert.equal(job.mailOptions.cc, 'sep@example.com');
  assert.equal(job.mailOptions.subject, 'Báo giá QT-001');
  assert.match(job.mailOptions.from, /^"Công ty ABC" <sales@thanhtung\.vn>$/);
  assert.deepEqual(job.attachmentRefs, [{ source: 'sales', id: 5, filename: 'QT-001.pdf', contentType: 'application/pdf' }]);
  assert.equal(JSON.stringify(job).includes('base64'), false);

  const record = await database.emailJobs.findOne({ _id: job.id });
  assert.equal(record.status, 'QUEUED');
  assert.equal(record.attachments_count, 1);
  assert.equal(record.source_event_type, 'QuotationEmailRequested');
  assert.equal(await database.notifications.countDocuments({ event_id: event.event_id }), 0);
});

test('the same event delivered twice queues the email only once', async () => {
  const { service, enqueued } = buildService();
  const event = emailEvent({}, 'SupplierInquiryEmailRequested');

  await service.handleEvent(event);
  const second = await service.handleEvent(event);

  assert.deepEqual(second, { skipped: 'DUPLICATE' });
  assert.equal(enqueued.length, 1);
  assert.equal(await database.emailJobs.countDocuments({ _id: event.payload.email.job_id }), 1);
});

test('a retried event (queue failed the first time) re-queues without duplicating the job record', async () => {
  let failOnce = true;
  const enqueued = [];
  const emailEvents = new EmailEventHandler({
    database, fromAddress: 'sales@thanhtung.vn', logger: silent,
    enqueue: async (job) => { if (failOnce) { failOnce = false; throw new Error('RabbitMQ down'); } enqueued.push(job); },
  });
  const service = new NotificationService({ repository: new NotificationRepository(database), resolver: {}, rules: {}, publisher: {}, emailEvents, logger: silent });
  const event = emailEvent();

  await assert.rejects(service.handleEvent(event), /RabbitMQ down/);
  assert.equal(await service.repository.isProcessed(event.event_id), false); // chưa đánh dấu đã xử lý: event sẽ được thử lại
  await service.handleEvent(event);

  assert.equal(enqueued.length, 1);
  assert.equal(await database.emailJobs.countDocuments({ _id: event.payload.email.job_id }), 1);
});

test('invalid email events are not retried; an identifiable job is recorded as FAILED', async () => {
  const { service, enqueued } = buildService();
  const cases = {
    'recipient with header injection': emailEvent({ email: { to: ['a@example.com\r\nBcc: x@evil.test'] } }),
    'no recipient': emailEvent({ email: { to: [] } }),
    'too many recipients': emailEvent({ email: { to: Array.from({ length: 51 }, (_, i) => `u${i}@example.com`) } }),
    'blank subject': emailEvent({ email: { subject: '  ' } }),
    'unknown attachment source': emailEvent({ attachments: [{ source: 'etc', id: 1 }] }),
    'attachment id not a number': emailEvent({ attachments: [{ source: 'sales', id: '1; drop' }] }),
    'to is not a list': emailEvent({ email: { to: 'a@example.com' } }),
  };

  for (const [name, event] of Object.entries(cases)) {
    assert.deepEqual(await service.handleEvent(event), { skipped: 'INVALID_EMAIL_EVENT' }, name);
    assert.equal((await database.emailJobs.findOne({ _id: event.payload.email.job_id })).status, 'FAILED', name);
  }
  assert.equal(enqueued.length, 0);

  const noJobId = emailEvent({ email: { job_id: 'not-a-uuid' } });
  assert.deepEqual(await service.handleEvent(noJobId), { skipped: 'INVALID_EMAIL_EVENT' });
});

test('sender name is neutralised and the body is escaped before becoming HTML', async () => {
  const { service, enqueued } = buildService();

  await service.handleEvent(emailEvent({ email: { sender_name: 'Evil"\r\nBcc: x@example.com <admin@bank.test>', subject: 'Chủ đề\r\nBcc: y@evil.test', body: '<script>alert(1)</script>\nDòng 2' } }));

  const { mailOptions } = enqueued[0];
  assert.doesNotMatch(mailOptions.from, /[\r\n]/);
  assert.equal(mailOptions.from.match(/"/g).length, 2);
  assert.doesNotMatch(mailOptions.subject, /[\r\n]/);
  assert.equal(mailOptions.html, '&lt;script&gt;alert(1)&lt;/script&gt;<br>Dòng 2');
  assert.equal(mailOptions.text, '<script>alert(1)</script>\nDòng 2');
});

test('worker downloads the attachments, sends once and records SMTP delivery', async () => {
  const { service, enqueued } = buildService();
  await service.handleEvent(emailEvent());
  const fetched = [];
  const fetchAttachment = async (ref) => { fetched.push(ref); return { filename: ref.filename, content: Buffer.from('%PDF'), contentType: ref.contentType }; };
  const sent = [];
  const transporter = { async sendMail(options) { sent.push(options); return { messageId: 'smtp-1' }; } };

  await sendQueuedEmail(database, enqueued[0], transporter, fetchAttachment);
  await sendQueuedEmail(database, enqueued[0], transporter, fetchAttachment);

  assert.equal(sent.length, 1);
  assert.equal(fetched.length, 1);
  assert.equal(sent[0].attachments[0].filename, 'QT-001.pdf');
  assert.equal(sent[0].attachments[0].content.toString(), '%PDF');
  const record = await database.emailJobs.findOne({ _id: enqueued[0].id });
  assert.equal(record.status, 'SENT');
  assert.equal(record.message_id, 'smtp-1');
});

test('a job queued before the event design (inline attachments, no references) is still sent with its attachments', async () => {
  const sent = [];
  const transporter = { async sendMail(options) { sent.push(options); return { messageId: 'legacy-1' }; } };
  const legacyJob = {
    id: randomUUID(),
    mailOptions: { from: 'x@y.z', to: 'a@example.com', subject: 'Job cũ', text: 'hi', attachments: [{ filename: 'old.pdf', content: 'JVBERg==', encoding: 'base64' }] },
  };

  await sendQueuedEmail(database, legacyJob, transporter, null);

  assert.equal(sent.length, 1);
  assert.deepEqual(sent[0].attachments, legacyJob.mailOptions.attachments);
});

test('an attachment that cannot be fetched fails the send so the queue retries it', async () => {
  const { service, enqueued } = buildService();
  await service.handleEvent(emailEvent());
  let sends = 0;
  const transporter = { async sendMail() { sends++; return { messageId: 'x' }; } };

  await assert.rejects(
    sendQueuedEmail(database, enqueued[0], transporter, async () => { throw new Error('sales-service không phản hồi'); }),
    /sales-service/,
  );
  assert.equal(sends, 0);
  assert.equal((await database.emailJobs.findOne({ _id: enqueued[0].id })).status, 'QUEUED');

  await markEmailFailed(database, enqueued[0], new Error('Hết số lần thử'));
  const record = await database.emailJobs.findOne({ _id: enqueued[0].id });
  assert.equal(record.status, 'FAILED');
  assert.equal(record.error, 'Hết số lần thử');
});

test('attachment fetcher calls the owning service with the internal key', async () => {
  const calls = [];
  const fetchImpl = async (url, options) => {
    calls.push({ url, key: options.headers['X-Internal-Service-Key'] });
    return { ok: true, status: 200, headers: new Map(), arrayBuffer: async () => new TextEncoder().encode('data').buffer };
  };
  const fetcher = createAttachmentFetcher({ services: { sales: 'http://sales:8005', purchase: 'http://purchase:8003' }, internalServiceKey: INTERNAL_KEY, fetchImpl });

  const file = await fetcher({ source: 'sales', id: 5, filename: 'a.pdf', contentType: 'application/pdf' });
  await fetcher({ source: 'purchase', id: 9, filename: 'b.pdf', contentType: 'application/pdf' });

  assert.deepEqual(calls, [
    { url: 'http://sales:8005/api/internal/attachments/5/download', key: INTERNAL_KEY },
    { url: 'http://purchase:8003/internal/inquiry-attachments/9', key: INTERNAL_KEY },
  ]);
  assert.equal(file.content.toString(), 'data');

  const missing = createAttachmentFetcher({ services: { sales: 'http://sales:8005' }, internalServiceKey: INTERNAL_KEY, fetchImpl: async () => ({ ok: false, status: 404 }) });
  await assert.rejects(missing({ source: 'sales', id: 1, filename: 'x.pdf' }), /HTTP 404/);
  await assert.rejects(fetcher({ source: 'unknown', id: 1, filename: 'x.pdf' }), /không hỗ trợ/);
});

test('there is no API to send email directly any more; only status lookups remain', async () => {
  const body = JSON.stringify({ to: ['a@example.com'], subject: 'x' });
  const asUser = await api('/send', { ...bearer(['quotation.create']), 'Content-Type': 'application/json' }, { method: 'POST', body });
  assert.equal(asUser.status, 404);
  // Khóa nội bộ chỉ dùng để tra cứu trạng thái; không có route nào nhận email từ nó
  const asService = await api('/send', { 'X-Internal-Service-Key': INTERNAL_KEY, 'Content-Type': 'application/json' }, { method: 'POST', body });
  assert.ok([401, 404].includes(asService.status), `unexpected ${asService.status}`);
});

test('email status API rejects requests without valid credentials', async () => {
  const cases = {
    'no credentials': {},
    'garbage token': { Authorization: 'Bearer not.a.jwt' },
    'wrong signature': bearer(['quotation.create'], { secret: 'other-secret' }),
    'expired token': bearer(['quotation.create'], { exp: Math.floor(Date.now() / 1000) - 10 }),
    'wrong issuer': bearer(['quotation.create'], { iss: 'someone-else' }),
    'wrong internal key': { 'X-Internal-Service-Key': 'wrong-key' },
    // Header internal sai thì không được "rơi" sang JWT hợp lệ
    'wrong internal key with valid jwt': { 'X-Internal-Service-Key': 'wrong-key', ...bearer(['quotation.create']) },
  };
  for (const [name, headers] of Object.entries(cases)) {
    assert.equal((await api('/logs/some-id', headers)).status, 401, name);
  }
  for (const path of ['/logs', '/logs/x']) {
    assert.equal((await api(path)).status, 401, path);
  }
});

test('status lookup needs a business permission; the full list needs a manager permission', async () => {
  const { service, enqueued } = buildService();
  await service.handleEvent(emailEvent());
  const id = enqueued.at(-1).id;

  assert.equal((await api(`/logs/${id}`, bearer(['inventory.view', 'catalog.view', 'quotation.view']))).status, 403);
  assert.equal((await api(`/logs/${id}`, bearer(['item-inquiry.view']))).status, 200);
  assert.equal((await api(`/logs/${id}`, { 'X-Internal-Service-Key': INTERNAL_KEY })).status, 200);
  assert.equal((await api('/logs', bearer(['quotation.create']))).status, 403);
  assert.equal((await api('/logs', bearer(['quotation.manage']))).status, 200);
  assert.equal((await api('/logs/does-not-exist', bearer(['item-inquiry.view']))).status, 404);
});

test('internal key is not accepted when no key is configured', async () => {
  const bare = createServer(createApp({ database, repository: {}, jwtSecret: SECRET }));
  bare.listen(0);
  await once(bare, 'listening');
  const response = await fetch(`http://127.0.0.1:${bare.address().port}/api/v1/notifications/email/logs`, { headers: { 'X-Internal-Service-Key': '' } });
  assert.equal(response.status, 401);
  bare.close();
});
