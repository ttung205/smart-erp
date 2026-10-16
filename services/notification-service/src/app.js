import express from 'express';
import cors from 'cors';
import { requireAuth } from './auth/jwt.js';
import { createEmailRouter } from './email/email.routes.js';
import { notificationRoutes } from './notifications/notification.routes.js';

/** Express app tách riêng khỏi http server / broker để test độc lập. */
export function createApp({ db, repository, publisher, jwtSecret, smtpInfo, enqueueEmail }) {
  const app = express();

  app.use(cors());
  app.use(express.json({ limit: '50mb' }));
  app.use(express.urlencoded({ extended: true, limit: '50mb' }));

  app.get('/health', (req, res) => {
    res.json({ status: 'UP', service: 'notification-service', timestamp: new Date().toISOString(), smtp: smtpInfo });
  });

  // Email gửi đồng bộ (giữ nguyên hành vi cũ) phải đăng ký trước router cần đăng nhập
  app.use('/api/v1/notifications/email', createEmailRouter({ db, enqueue: enqueueEmail }));
  app.use('/api/v1/notifications', requireAuth(jwtSecret), notificationRoutes({ repository, publisher }));

  return app;
}
