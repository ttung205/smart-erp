import { transaction } from '../db.js';

/**
 * Xử lý 1 domain event: tra rule → xác định người nhận → ghi DB (idempotent) → đẩy realtime.
 * Ném lỗi khi cần retry (vd identity-service chưa phản hồi); message chỉ được ACK khi hàm này xong.
 */
export class NotificationService {
  constructor({ db, repository, resolver, rules, publisher, logger = console }) {
    this.db = db;
    this.repository = repository;
    this.resolver = resolver;
    this.rules = rules;
    this.publisher = publisher;
    this.logger = logger;
  }

  async handleEvent(envelope) {
    const { event_id: eventId, event_type: eventType } = envelope || {};
    if (!eventId || !eventType) {
      this.logger.warn('[Notification] Bỏ qua message thiếu event_id/event_type');
      return { skipped: 'INVALID_ENVELOPE' };
    }
    if (this.repository.isProcessed(eventId)) {
      return { skipped: 'DUPLICATE' };
    }

    // Đẩy broadcast Socket.IO đồng bộ dữ liệu thời gian thực cho frontend (Giai đoạn 3)
    if (eventType === 'ProductCreated') {
      this.publisher?.broadcast?.('catalog:product_created', envelope.payload || {});
    }
    if (eventType === 'StockUpdated' || eventType === 'StockReserved') {
      this.publisher?.broadcast?.('inventory:stock_updated', envelope.payload || {});
    }
    if (eventType === 'GoodsIssued') {
      // Payload GoodsIssued (issue_id, warehouse_id, items[].balance_*_after) không có `reservations`
      // nên store frontend không cộng trừ giữ chỗ lần nữa; chỉ báo hiệu tồn kho đã đổi.
      this.publisher?.broadcast?.('inventory:stock_updated', { event_type: eventType, ...(envelope.payload || {}) });
    }

    const rule = this.rules[eventType];
    if (!rule) {
      this.repository.markProcessed(eventId, eventType);
      return { skipped: 'NO_RULE' };
    }

    const payload = envelope.payload || {};
    const actorId = payload.actor?.id;
    const recipientIds = await this.resolver.resolve({
      ...rule.recipients(payload),
      excludeUserIds: actorId ? [actorId] : [],
    });

    const notification = rule.title
      ? {
          type: eventType,
          category: rule.category,
          title: rule.title(payload),
          body: rule.body?.(payload),
          link: rule.link?.(payload),
          actionKey: rule.actionKey?.(payload),
          actor: payload.actor,
          metadata: { aggregate_type: envelope.aggregate_type, aggregate_id: envelope.aggregate_id, correlation_id: envelope.correlation_id },
        }
      : null;

    const { created, resolved } = transaction(this.db, () => {
      const resolvedRows = this.repository.resolveActions(rule.resolves?.(payload) ?? []);
      const createdRows = notification ? this.repository.insertMany(eventId, recipientIds, notification) : [];
      this.repository.markProcessed(eventId, eventType);
      return { created: createdRows, resolved: resolvedRows };
    });

    this.pushRealtime(created, resolved);
    return { created: created.length, resolved: resolved.length };
  }

  pushRealtime(created, resolved) {
    if (!this.publisher) return;
    const touched = new Set();
    for (const { recipientId, notification } of created) {
      this.publisher.toUser(recipientId, 'notification:new', notification);
      touched.add(recipientId);
    }
    for (const { recipientId, id } of resolved) {
      this.publisher.toUser(recipientId, 'notification:resolved', { id });
      touched.add(recipientId);
    }
    for (const recipientId of touched) {
      this.publisher.toUser(recipientId, 'notification:unread_count', { unread_count: this.repository.unreadCount(recipientId) });
    }
  }
}
