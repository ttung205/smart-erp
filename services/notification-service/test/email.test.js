import assert from 'node:assert/strict';
import { once } from 'node:events';
import { createServer } from 'node:http';
import { after, before, test } from 'node:test';
import { createApp } from '../src/app.js';
import { openDatabase } from '../src/db.js';
import { markEmailFailed, sendQueuedEmail } from '../src/email/email.routes.js';

const db = openDatabase(':memory:');
const jobs = [];
const server = createServer(createApp({ db, repository: {}, jwtSecret: '', enqueueEmail: async (job) => jobs.push(job) }));
let baseUrl;

before(async () => {
  server.listen(0);
  await once(server, 'listening');
  baseUrl = `http://127.0.0.1:${server.address().port}`;
});
after(async () => { server.close(); db.close(); });

test('email API queues a job and worker records SMTP delivery once', async () => {
  const response = await fetch(`${baseUrl}/api/v1/notifications/email/send`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
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
  const logs = await (await fetch(`${baseUrl}/api/v1/notifications/email/logs`)).json();
  assert.equal(logs.data[0].status, 'SENT');
  assert.equal(logs.data[0].messageId, 'smtp-1');
});

test('failed email is recorded for inspection', () => {
  markEmailFailed(db, { id: 'failed-job', mailOptions: { to: 'b@example.com', subject: 'Fail' } }, new Error('SMTP unavailable'));
  assert.equal(db.prepare('SELECT status FROM email_jobs WHERE id = ?').get('failed-job').status, 'FAILED');
});
