import { ObjectId } from 'mongodb';

const iso = (value) => (value ? new Date(value).toISOString() : null);

function toDto(doc) {
  if (!doc) return null;
  return {
    id: doc._id.toString(),
    type: doc.type,
    category: doc.category,
    title: doc.title,
    body: doc.body ?? null,
    link: doc.link ?? null,
    action_key: doc.action_key ?? null,
    status: doc.status,
    actor: doc.actor_id ? { id: doc.actor_id, name: doc.actor_name } : null,
    metadata: doc.metadata ?? {},
    created_at: iso(doc.created_at),
    read_at: iso(doc.read_at),
    resolved_at: iso(doc.resolved_at),
  };
}

/** id không đúng định dạng ObjectId thì coi như không tồn tại (404), không ném lỗi. */
function toObjectId(id) {
  return ObjectId.isValid(id) && String(new ObjectId(id)) === String(id) ? new ObjectId(id) : null;
}

export class NotificationRepository {
  constructor(database) {
    this.notifications = database.notifications;
    this.processedEvents = database.processedEvents;
  }

  async isProcessed(eventId) {
    return Boolean(await this.processedEvents.findOne({ _id: eventId }, { projection: { _id: 1 } }));
  }

  async markProcessed(eventId, eventType) {
    await this.processedEvents.updateOne(
      { _id: eventId },
      { $setOnInsert: { event_type: eventType, processed_at: new Date() } },
      { upsert: true },
    );
  }

  /**
   * Ghi 1 bản ghi/người nhận; trùng (event_id, recipient_id) thì bỏ qua nhờ unique index.
   * Trả về các bản ghi vừa tạo mới.
   */
  async insertMany(eventId, recipientIds, notification) {
    const createdAt = new Date();
    const created = [];
    for (const recipientId of recipientIds) {
      const result = await this.notifications.updateOne(
        { event_id: eventId, recipient_id: recipientId },
        {
          $setOnInsert: {
            type: notification.type,
            category: notification.category,
            title: notification.title,
            body: notification.body ?? null,
            link: notification.link ?? null,
            action_key: notification.actionKey ?? null,
            status: 'UNREAD',
            actor_id: notification.actor?.id ?? null,
            actor_name: notification.actor?.name ?? null,
            metadata: notification.metadata ?? {},
            created_at: createdAt,
            read_at: null,
            resolved_at: null,
          },
        },
        { upsert: true },
      );
      if (result.upsertedId) {
        created.push({ recipientId, notification: await this.find(result.upsertedId) });
      }
    }
    return created;
  }

  /** Đánh dấu "đã xử lý" mọi việc cần làm có action_key; trả về [{recipientId, id}] để đẩy realtime. */
  async resolveActions(actionKeys) {
    if (actionKeys.length === 0) return [];
    const filter = { action_key: { $in: actionKeys }, resolved_at: null };
    const docs = await this.notifications.find(filter, { projection: { recipient_id: 1 } }).toArray();
    if (docs.length === 0) return [];
    await this.notifications.updateMany(
      { _id: { $in: docs.map((doc) => doc._id) }, resolved_at: null },
      { $set: { status: 'RESOLVED', resolved_at: new Date() } },
    );
    return docs.map((doc) => ({ id: doc._id.toString(), recipientId: doc.recipient_id }));
  }

  async find(id) {
    const objectId = id instanceof ObjectId ? id : toObjectId(id);
    return objectId ? toDto(await this.notifications.findOne({ _id: objectId })) : null;
  }

  async list(recipientId, { status, category, beforeId, limit = 20 } = {}) {
    const filter = { recipient_id: recipientId };
    if (status === 'unread') filter.status = 'UNREAD';
    if (category) filter.category = category;
    if (beforeId) {
      const before = toObjectId(beforeId);
      if (before) filter._id = { $lt: before };
    }
    const docs = await this.notifications
      .find(filter)
      .sort({ _id: -1 })
      .limit(Math.min(Math.max(Number(limit) || 20, 1), 100))
      .toArray();
    return docs.map(toDto);
  }

  unreadCount(recipientId) {
    return this.notifications.countDocuments({ recipient_id: recipientId, status: 'UNREAD' });
  }

  /** Việc cần làm đã RESOLVED vẫn giữ nguyên trạng thái, chỉ ghi nhận thời điểm đọc. */
  async markRead(recipientId, id) {
    const objectId = toObjectId(id);
    if (!objectId) return null;
    const result = await this.notifications.updateOne({ _id: objectId, recipient_id: recipientId }, [
      {
        $set: {
          status: { $cond: [{ $eq: ['$status', 'UNREAD'] }, 'READ', '$status'] },
          read_at: { $ifNull: ['$read_at', new Date()] },
        },
      },
    ]);
    return result.matchedCount > 0 ? this.find(objectId) : null;
  }

  async markAllRead(recipientId) {
    const result = await this.notifications.updateMany(
      { recipient_id: recipientId, status: 'UNREAD' },
      { $set: { status: 'READ', read_at: new Date() } },
    );
    return result.modifiedCount;
  }
}
