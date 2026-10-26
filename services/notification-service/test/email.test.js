import { createHmac } from 'node:crypto';
import assert from 'node:assert/strict';
import { once } from 'node:events';
import { createServer } from 'node:http';
import { after, before, test } from 'node:test';
import { createApp } from '../src/app.js';
import { openDatabase } from '../src/db.js';
import { markEmailFailed, sendQueuedEmail } from '../src/email/email.routes.js';

const db = openDatabase(':memory:');
const jobs = [];
const SECRET = 'email-test-secret';
const INTERNAL_KEY = 'email-test-internal-key';
const server = createServer(createApp({ db, repository: {}, jwtSecret: SECRET, internalServiceKey: INTERNAL_KEY, enqueueEmail: async (job) => jobs.push(job) }));

function token(permissions, { secret = SECRET, exp = Math.floor(Date.now() / 1000) + 3600, iss = 'identity-service' } = {}) {
  const encode = (value) => Buffer.from(JSON.stringify(value)).toString('base64url');
  const header = encode({ typ: 'JWT', alg: 'HS256' });
  const payload = encode({ iss, sub: '7', name: 'User 7', permissions, exp });
  return `${header}.${payload}.${createHmac('sha256', secret).update(`${header}.${payload}`).digest('base64url')}`;
}
const bearer = (permissions, options) => ({ Authorization: `Bearer ${token(permissions, options)}` });
const sender = bearer(['quotation.create']);
const mail = { to: ['a@example.com'], subject: 'Quotation', body: 'Hello' };
const post = (headers = {}, body = mail) => fetch(`${baseUrl}/api/v1/notifications/email/send`, {
  method: 'POST', headers: { 'Content-Type': 'application/json', ...headers }, body: JSON.stringify(body),
});
let baseUrl;

before(async () => {
  server.listen(0);
  await once(server, 'listening');
  baseUrl = `http://127.0.0.1:${server.address().port}`;
});
after(async () => { server.close(); db.close(); });

test('email API queues a job and worker records SMTP delivery once', async () => {
  const response = await fetch(`${baseUrl}/api/v1/notifications/email/send`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', ...sender },
    body: JSON.stringify({ to: ['a@example.com'], subject: 'Quotation', body: 'Hello',
      attachments: [{ filename: 'q.pdf', content: 'JVBERg==', encoding: 'base64' }] }),
  });
  assert.equal(response.status, 202);
  const { data } = await response.json();
  assert.equal(data.status, 'QUEUED');
  assert.equal(jobs.length, 1);
  assert.equal(jobs[0].mailOptions.attachments[0].encoding, 'base64');
  let sends = 0;
  const transporter = { async sendMail() { sends++; return { messageId: 'smtp-1' }; } };
  await sendQueuedEmail(db, jobs[0], transporter);
  await sendQueuedEmail(db, jobs[0], transporter);
  assert.equal(sends, 1);
  const logs = await (await fetch(`${baseUrl}/api/v1/notifications/email/logs`, { headers: bearer(['quotation.manage']) })).json();
  assert.equal(logs.data[0].status, 'SENT');
  assert.equal(logs.data[0].messageId, 'smtp-1');
});

test('failed email is recorded for inspection', () => {
  markEmailFailed(db, { id: 'failed-job', mailOptions: { to: 'b@example.com', subject: 'Fail' } }, new Error('SMTP unavailable'));
  assert.equal(db.prepare('SELECT status FROM email_jobs WHERE id = ?').get('failed-job').status, 'FAILED');
});

test('email API rejects requests without valid credentials and queues nothing', async () => {
  const before = jobs.length;
  const cases = {
    'no credentials': {},
    'garbage token': { Authorization: 'Bearer not.a.jwt' },
    'wrong signature': bearer(['quotation.create'], { secret: 'other-secret' }),
    'expired token': bearer(['quotation.create'], { exp: Math.floor(Date.now() / 1000) - 10 }),
    'wrong issuer': bearer(['quotation.create'], { iss: 'someone-else' }),
    'wrong internal key': { 'X-Internal-Service-Key': 'wrong-key' },
    // Header internal sai thì không được "rơi" sang JWT hợp lệ
    'wrong internal key with valid jwt': { 'X-Internal-Service-Key': 'wrong-key', ...sender },
  };
  for (const [name, headers] of Object.entries(cases)) {
    const response = await post(headers);
    assert.equal(response.status, 401, name);
  }
  for (const path of ['/logs', '/logs/x']) {
    assert.equal((await fetch(`${baseUrl}/api/v1/notifications/email${path}`)).status, 401, path);
  }
  assert.equal(jobs.length, before);
});

test('authenticated user without an email-related permission cannot send or list', async () => {
  const before = jobs.length;
  for (const permissions of [[], ['inventory.view', 'catalog.view', 'quotation.view']]) {
    assert.equal((await post(bearer(permissions))).status, 403);
  }
  assert.equal((await fetch(`${baseUrl}/api/v1/notifications/email/logs`, { headers: bearer(['quotation.create']) })).status, 403);
  assert.equal(jobs.length, before);
});

test('user with item-inquiry permission can send and read one job status, internal key is accepted', async () => {
  const sent = await post(bearer(['item-inquiry.create']), { ...mail, subject: 'Inquiry' });
  assert.equal(sent.status, 202);
  const { data } = await sent.json();
  const status = await fetch(`${baseUrl}/api/v1/notifications/email/logs/${data.id}`, { headers: bearer(['item-inquiry.view']) });
  assert.equal(status.status, 200);

  const internal = await post({ 'X-Internal-Service-Key': INTERNAL_KEY }, { ...mail, subject: 'Internal' });
  assert.equal(internal.status, 202);
});

test('email API limits recipients and neutralises header injection in the sender name', async () => {
  const many = await post(sender, { ...mail, to: Array.from({ length: 51 }, (_, i) => `u${i}@example.com`) });
  assert.equal(many.status, 422);

  const before = jobs.length;
  const response = await post(sender, { ...mail, senderName: 'Evil"\r\nBcc: x@example.com <admin@bank.test>' });
  assert.equal(response.status, 202);
  const from = jobs[before].mailOptions.from;
  assert.doesNotMatch(from, /[\r\n]/);
  assert.equal(from.match(/"/g).length, 2);
  assert.match(from, /<sales@thanhtung\.vn>$/);
});

test('email auth is disabled for internal key when no key is configured', async () => {
  const bare = createServer(createApp({ db, repository: {}, jwtSecret: SECRET, enqueueEmail: async () => {} }));
  bare.listen(0);
  await once(bare, 'listening');
  const response = await fetch(`http://127.0.0.1:${bare.address().port}/api/v1/notifications/email/send`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Internal-Service-Key': '' }, body: JSON.stringify(mail),
  });
  assert.equal(response.status, 401);
  bare.close();
});
