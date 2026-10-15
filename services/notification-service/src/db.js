import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
import { DatabaseSync } from 'node:sqlite';

const SCHEMA = `
CREATE TABLE IF NOT EXISTS notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  recipient_id INTEGER NOT NULL,
  event_id TEXT NOT NULL,
  type TEXT NOT NULL,
  category TEXT NOT NULL,
  title TEXT NOT NULL,
  body TEXT,
  link TEXT,
  action_key TEXT,
  status TEXT NOT NULL DEFAULT 'UNREAD',
  actor_id INTEGER,
  actor_name TEXT,
  metadata TEXT,
  created_at TEXT NOT NULL,
  read_at TEXT,
  resolved_at TEXT,
  UNIQUE (event_id, recipient_id)
);
CREATE INDEX IF NOT EXISTS idx_notifications_inbox ON notifications (recipient_id, status, id);
CREATE INDEX IF NOT EXISTS idx_notifications_action ON notifications (action_key, resolved_at);

CREATE TABLE IF NOT EXISTS processed_events (
  event_id TEXT PRIMARY KEY,
  event_type TEXT NOT NULL,
  processed_at TEXT NOT NULL
);
`;

/** Mở (hoặc tạo) DB SQLite của notification-service. Truyền ':memory:' khi test. */
export function openDatabase(path) {
  if (path !== ':memory:') {
    mkdirSync(dirname(path), { recursive: true });
  }
  const db = new DatabaseSync(path);
  db.exec('PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
  db.exec(SCHEMA);
  return db;
}

/** Chạy fn trong 1 transaction; lỗi thì rollback. */
export function transaction(db, fn) {
  db.exec('BEGIN');
  try {
    const result = fn();
    db.exec('COMMIT');
    return result;
  } catch (error) {
    db.exec('ROLLBACK');
    throw error;
  }
}
