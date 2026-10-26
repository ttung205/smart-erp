import { MongoClient } from 'mongodb';

/** processed_events chỉ cần nhớ đủ lâu để chặn message gửi lặp; quá hạn thì Mongo tự xóa. */
const PROCESSED_EVENT_TTL_SECONDS = 30 * 24 * 60 * 60;

/**
 * Kết nối MongoDB của notification-service và đảm bảo index.
 * - notifications: UNIQUE (event_id, recipient_id) chống ghi trùng khi event bị gửi lặp.
 * - processed_events: _id = event_id; TTL tự dọn.
 * - email_jobs: _id = id của job (uuid).
 * Truyền dbName riêng khi test để mỗi test chạy trên database độc lập.
 */
export async function openDatabase(uri, dbName) {
  const client = new MongoClient(uri);
  await client.connect();
  const db = client.db(dbName);

  const notifications = db.collection('notifications');
  const processedEvents = db.collection('processed_events');
  const emailJobs = db.collection('email_jobs');

  await Promise.all([
    notifications.createIndex({ event_id: 1, recipient_id: 1 }, { unique: true }),
    notifications.createIndex({ recipient_id: 1, status: 1, _id: -1 }),
    notifications.createIndex({ action_key: 1, resolved_at: 1 }),
    processedEvents.createIndex({ processed_at: 1 }, { expireAfterSeconds: PROCESSED_EVENT_TTL_SECONDS }),
    emailJobs.createIndex({ created_at: -1 }),
  ]);

  return {
    client,
    db,
    notifications,
    processedEvents,
    emailJobs,
    close: () => client.close(),
    /** Chỉ dùng trong test: xóa toàn bộ database tạm. */
    drop: () => db.dropDatabase(),
  };
}
