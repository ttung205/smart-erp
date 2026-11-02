import express from 'express';
import cors from 'cors';
import { requireAuth } from './auth/jwt.js';
import { authenticateEmailCaller } from './email/email.auth.js';
import { createEmailRouter } from './email/email.routes.js';
import { notificationRoutes } from './notifications/notification.routes.js';

/** Express app tách riêng khỏi http server / broker để test độc lập. */
export function createApp({ database, repository, publisher, jwtSecret, internalServiceKey = '', smtpInfo }) {
  const app = express();

  app.use(cors());
  // Xác thực email TRƯỚC khi parse body để request không có credentials bị từ chối sớm
  app.use('/api/v1/notifications/email', authenticateEmailCaller({ jwtSecret, internalServiceKey }));
  app.use(express.json({ limit: '1mb' }));
  app.use(express.urlencoded({ extended: true, limit: '1mb' }));

  app.get(['/health', '/api/v1/notifications/health'], (req, res) => {
    res.json({ status: 'UP', service: 'notification-service', timestamp: new Date().toISOString(), smtp: smtpInfo });
  });

  // Email: đã xác thực ở trên, mỗi route kiểm tra quyền riêng; đăng ký trước router thông báo chung
  app.use('/api/v1/notifications/email', createEmailRouter({ database }));
  app.use('/api/v1/notifications', requireAuth(jwtSecret), notificationRoutes({ repository, publisher }));

  return app;
}
