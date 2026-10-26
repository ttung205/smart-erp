import { Router } from 'express';

/** Express 4 không tự bắt lỗi của handler async. */
const wrap = (handler) => (req, res, next) => Promise.resolve(handler(req, res, next)).catch(next);

/** Hộp thư thông báo của người đang đăng nhập (mọi truy vấn đều lọc theo req.actor.id). */
export function notificationRoutes({ repository, publisher }) {
  const router = Router();

  router.get('/', wrap(async (req, res) => {
    const { status, category, before_id: beforeId, limit } = req.query;
    const data = await repository.list(req.actor.id, {
      status,
      category,
      beforeId,
      limit,
    });
    res.json({ success: true, data, meta: { unread_count: await repository.unreadCount(req.actor.id) } });
  }));

  router.get('/unread-count', wrap(async (req, res) => {
    res.json({ success: true, data: { unread_count: await repository.unreadCount(req.actor.id) } });
  }));

  router.post('/read-all', wrap(async (req, res) => {
    const updated = await repository.markAllRead(req.actor.id);
    publisher?.toUser(req.actor.id, 'notification:unread_count', { unread_count: 0 });
    res.json({ success: true, data: { updated } });
  }));

  router.post('/:id/read', wrap(async (req, res) => {
    const notification = await repository.markRead(req.actor.id, req.params.id);
    if (!notification) {
      return res.status(404).json({ success: false, error: { code: 'NOT_FOUND', message: 'Không tìm thấy thông báo.' } });
    }
    const unreadCount = await repository.unreadCount(req.actor.id);
    // Đồng bộ badge giữa các tab đang mở của cùng người dùng
    publisher?.toUser(req.actor.id, 'notification:unread_count', { unread_count: unreadCount });
    return res.json({ success: true, data: notification, meta: { unread_count: unreadCount } });
  }));

  return router;
}
