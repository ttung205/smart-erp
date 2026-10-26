import dotenv from 'dotenv';

dotenv.config();

export const config = {
  port: Number(process.env.PORT) || 8006,
  jwtSecret: process.env.JWT_SECRET || '',
  // directConnection: Mongo chạy replica set một node, tên member (mongo:27017) không phân giải được từ máy dev
  mongo: {
    uri: process.env.MONGODB_URI || 'mongodb://127.0.0.1:27017/?directConnection=true',
    dbName: process.env.NOTIFICATION_DB_NAME || 'erp_notification_db',
  },
  identityUrl: (process.env.IDENTITY_SERVICE_URL || 'http://127.0.0.1:8001').replace(/\/$/, ''),
  internalServiceKey: process.env.INTERNAL_SERVICE_KEY || '',
  recipientCacheTtlMs: Number(process.env.RECIPIENT_CACHE_TTL_MS) || 5 * 60 * 1000,
  rabbitmq: {
    url: process.env.RABBITMQ_URL || 'amqp://erp_rabbit:rabbit_secret@127.0.0.1:5672',
    exchange: 'erp.events',
    deadLetterExchange: 'erp.dlx',
    queue: 'erp.notification.events',
    retryQueue: 'erp.notification.retry',
    retryDelayMs: 10000,
    maxRetries: 5,
    prefetch: 10,
    emailQueue: 'erp.email.send',
    emailRetryQueue: 'erp.email.retry',
  },
};
