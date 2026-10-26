import { createServer } from 'node:http';
import { createApp } from './app.js';
import { config } from './config.js';
import { openDatabase } from './db.js';
import { startConsumer } from './messaging/consumer.js';
import { enqueueEmail, startEmailConsumer } from './email/email.queue.js';
import { markEmailFailed, sendQueuedEmail } from './email/email.routes.js';
import { NotificationRepository } from './notifications/notification.repository.js';
import { NotificationService } from './notifications/notification.service.js';
import { RecipientResolver } from './notifications/recipient-resolver.js';
import { rules } from './notifications/rules.js';
import { createRealtime } from './realtime/socket.js';

if (!config.jwtSecret) {
  console.warn('[Notification Service] Chưa cấu hình JWT_SECRET: API hộp thư và socket sẽ từ chối mọi kết nối.');
}
if (!config.internalServiceKey) {
  console.warn('[Notification Service] Chưa cấu hình INTERNAL_SERVICE_KEY: identity-service sẽ từ chối tra người nhận theo quyền.');
}

const database = await openDatabase(config.mongo.uri, config.mongo.dbName);
const repository = new NotificationRepository(database);

let realtime = null;
const publisher = {
  toUser: (...args) => realtime?.toUser(...args),
  broadcast: (...args) => realtime?.broadcast(...args),
};

const app = createApp({
  database,
  repository,
  publisher,
  jwtSecret: config.jwtSecret,
  internalServiceKey: config.internalServiceKey,
  enqueueEmail: (job) => enqueueEmail(config.rabbitmq, job),
  smtpInfo: {
    host: process.env.SMTP_HOST || '127.0.0.1',
    port: Number(process.env.SMTP_PORT) || 1025,
    from: process.env.MAIL_FROM_ADDRESS || 'sales@thanhtung.vn',
  },
});
const server = createServer(app);
realtime = createRealtime(server, { jwtSecret: config.jwtSecret });

const notificationService = new NotificationService({
  repository,
  resolver: new RecipientResolver({
    identityUrl: config.identityUrl,
    internalServiceKey: config.internalServiceKey,
    cacheTtlMs: config.recipientCacheTtlMs,
  }),
  rules,
  publisher,
});
const consumer = startConsumer({ rabbitmq: config.rabbitmq, handle: (envelope) => notificationService.handleEvent(envelope) });
const emailConsumer = startEmailConsumer({
  rabbitmq: config.rabbitmq,
  handle: (job) => sendQueuedEmail(database, job),
  onFailed: (job, error) => markEmailFailed(database, job, error),
});

server.listen(config.port, () => {
  console.log(`[Notification Service] Running on port ${config.port}`);
  console.log(`[Notification Service] SMTP: ${process.env.SMTP_HOST || '127.0.0.1'}:${process.env.SMTP_PORT || 1025}`);
});

async function shutdown() {
  await consumer.stop();
  await emailConsumer.stop();
  realtime.io.close();
  server.close(async () => {
    await database.close();
    process.exit(0);
  });
}
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
