const nowIso = () => new Date().toISOString();

function toDto(row) {
  if (!row) return null;
  return {
    id: row.id,
    type: row.type,
    category: row.category,
    title: row.title,
    body: row.body,
    link: row.link,
    action_key: row.action_key,
    status: row.status,
    actor: row.actor_id ? { id: row.actor_id, name: row.actor_name } : null,
    metadata: row.metadata ? JSON.parse(row.metadata) : {},
    created_at: row.created_at,
    read_at: row.read_at,
    resolved_at: row.resolved_at,
  };
}

export class NotificationRepository {
  constructor(db) {
    this.db = db;
  }

  isProcessed(eventId) {
    return Boolean(this.db.prepare('SELECT 1 FROM processed_events WHERE event_id = ?').get(eventId));
  }

  markProcessed(eventId, eventType) {
    this.db
      .prepare('INSERT OR IGNORE INTO processed_events (event_id, event_type, processed_at) VALUES (?, ?, ?)')
      .run(eventId, eventType, nowIso());
  }

  /** Ghi 1 dòng/người nhận; trùng (event_id, recipient_id) thì bỏ qua. Trả về các bản ghi vừa tạo. */
  insertMany(eventId, recipientIds, notification) {
    const insert = this.db.prepare(`
      INSERT OR IGNORE INTO notifications
        (recipient_id, event_id, type, category, title, body, link, action_key, actor_id, actor_name, metadata, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `);
    const createdAt = nowIso();
    const created = [];
    for (const recipientId of recipientIds) {
      const result = insert.run(
        recipientId,
        eventId,
        notification.type,
        notification.category,
        notification.title,
        notification.body ?? null,
        notification.link ?? null,
        notification.actionKey ?? null,
        notification.actor?.id ?? null,
        notification.actor?.name ?? null,
        JSON.stringify(notification.metadata ?? {}),
        createdAt,
      );
      if (result.changes > 0) {
        created.push({ recipientId, notification: this.find(Number(result.lastInsertRowid)) });
      }
    }
    return created;
  }

  /** Đánh dấu "đã xử lý" mọi việc cần làm có action_key; trả về [{recipientId, id}] để đẩy realtime. */
  resolveActions(actionKeys) {
    if (actionKeys.length === 0) return [];
    const placeholders = actionKeys.map(() => '?').join(', ');
    const rows = this.db
      .prepare(`SELECT id, recipient_id FROM notifications WHERE action_key IN (${placeholders}) AND resolved_at IS NULL`)
      .all(...actionKeys);
    if (rows.length === 0) return [];
    this.db
      .prepare(`UPDATE notifications SET status = 'RESOLVED', resolved_at = ? WHERE action_key IN (${placeholders}) AND resolved_at IS NULL`)
      .run(nowIso(), ...actionKeys);
    return rows.map((row) => ({ id: row.id, recipientId: row.recipient_id }));
  }

  find(id) {
    return toDto(this.db.prepare('SELECT * FROM notifications WHERE id = ?').get(id));
  }

  list(recipientId, { status, category, beforeId, limit = 20 } = {}) {
    const where = ['recipient_id = ?'];
    const params = [recipientId];
    if (status === 'unread') where.push("status = 'UNREAD'");
    if (category) {
      where.push('category = ?');
      params.push(category);
    }
    if (beforeId) {
      where.push('id < ?');
      params.push(beforeId);
    }
    const rows = this.db
      .prepare(`SELECT * FROM notifications WHERE ${where.join(' AND ')} ORDER BY id DESC LIMIT ?`)
      .all(...params, Math.min(Math.max(Number(limit) || 20, 1), 100));
    return rows.map(toDto);
  }

  unreadCount(recipientId) {
    return this.db
      .prepare("SELECT COUNT(*) AS total FROM notifications WHERE recipient_id = ? AND status = 'UNREAD'")
      .get(recipientId).total;
  }

  /** Việc cần làm đã RESOLVED vẫn giữ nguyên trạng thái, chỉ ghi nhận thời điểm đọc. */
  markRead(recipientId, id) {
    const readAt = nowIso();
    const result = this.db
      .prepare("UPDATE notifications SET status = CASE WHEN status = 'UNREAD' THEN 'READ' ELSE status END, read_at = COALESCE(read_at, ?) WHERE id = ? AND recipient_id = ?")
      .run(readAt, id, recipientId);
    return result.changes > 0 ? this.find(id) : null;
  }

  markAllRead(recipientId) {
    return this.db
      .prepare("UPDATE notifications SET status = 'READ', read_at = ? WHERE recipient_id = ? AND status = 'UNREAD'")
      .run(nowIso(), recipientId).changes;
  }
}
