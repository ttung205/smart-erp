import { Router } from 'express';

/** Hộp thư thông báo của người đang đăng nhập (mọi truy vấn đều lọc theo req.actor.id). */
export function notificationRoutes({ repository, publisher }) {
  const router = Router();

  router.get('/', (req, res) => {
    const { status, category, before_id: beforeId, limit } = req.query;
    const data = repository.list(req.actor.id, {
      status,
      category,
      beforeId: beforeId ? Number(beforeId) : undefined,
      limit,
    });
    res.json({ success: true, data, meta: { unread_count: repository.unreadCount(req.actor.id) } });
  });

  router.get('/unread-count', (req, res) => {
    res.json({ success: true, data: { unread_count: repository.unreadCount(req.actor.id) } });
  });

  router.post('/read-all', (req, res) => {
    const updated = repository.markAllRead(req.actor.id);
    publisher?.toUser(req.actor.id, 'notification:unread_count', { unread_count: 0 });
    res.json({ success: true, data: { updated } });
  });

  router.post('/:id/read', (req, res) => {
    const notification = repository.markRead(req.actor.id, Number(req.params.id));
    if (!notification) {
      return res.status(404).json({ success: false, error: { code: 'NOT_FOUND', message: 'Không tìm thấy thông báo.' } });
    }
    const unreadCount = repository.unreadCount(req.actor.id);
    // Đồng bộ badge giữa các tab đang mở của cùng người dùng
    publisher?.toUser(req.actor.id, 'notification:unread_count', { unread_count: unreadCount });
    return res.json({ success: true, data: notification, meta: { unread_count: unreadCount } });
  });

  return router;
}
