import { randomUUID } from 'node:crypto';
import { openDatabase } from '../../src/db.js';

/** Test cần MongoDB thật (docker compose up -d mongo); mỗi lần gọi dùng database riêng, xóa bằng database.drop(). */
export const TEST_MONGODB_URI = process.env.MONGODB_URI || 'mongodb://127.0.0.1:27017/?directConnection=true';

export function openTestDatabase() {
  return openDatabase(TEST_MONGODB_URI, `erp_notification_test_${randomUUID().replaceAll('-', '')}`);
}
