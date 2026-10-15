import { createHmac } from 'node:crypto';
import { once } from 'node:events';
import { createServer } from 'node:http';
import assert from 'node:assert/strict';
import { beforeEach, describe, it } from 'node:test';
import { createApp } from '../src/app.js';
import { openDatabase } from '../src/db.js';
import { NotificationRepository } from '../src/notifications/notification.repository.js';
import { NotificationService } from '../src/notifications/notification.service.js';
import { RecipientResolver } from '../src/notifications/recipient-resolver.js';
import { rules } from '../src/notifications/rules.js';

const SECRET = 'notification-test-secret';
const silentLogger = { log() {}, warn() {}, error() {} };

function envelope(eventType, payload, eventId = `evt-${Math.random()}`) {
  return { event_id: eventId, event_type: eventType, aggregate_type: 'X', aggregate_id: '1', correlation_id: 'corr', payload };
}

function token(userId) {
  const encode = (value) => Buffer.from(JSON.stringify(value)).toString('base64url');
  const header = encode({ typ: 'JWT', alg: 'HS256' });
  const payload = encode({ iss: 'identity-service', sub: String(userId), name: `User ${userId}`, exp: Math.floor(Date.now() / 1000) + 3600 });
  const signature = createHmac('sha256', SECRET).update(`${header}.${payload}`).digest('base64url');
  return `${header}.${payload}.${signature}`;
}

/** identity-service giả: user 1, 2, 3 có quyền duyệt báo giá. */
function fakeIdentity(usersByPermission, calls = []) {
  return async (url) => {
    calls.push(url);
    const permissions = decodeURIComponent(new URL(url).searchParams.get('permissions')).split(',');
    const ids = [...new Set(permissions.flatMap((code) => usersByPermission[code] || []))];
    return { ok: true, json: async () => ({ data: ids.map((id) => ({ id })) }) };
  };
}

let db;
let repository;
let pushes;
let service;
let identityCalls;

function buildService(fetchImpl) {
  return new NotificationService({
    db,
    repository,
    resolver: new RecipientResolver({ identityUrl: 'http://identity', cacheTtlMs: 60000, fetchImpl }),
    rules,
    publisher: { toUser: (userId, event, data) => pushes.push({ userId, event, data }) },
    logger: silentLogger,
  });
}

beforeEach(() => {
  db = openDatabase(':memory:');
  repository = new NotificationRepository(db);
  pushes = [];
  identityCalls = [];
  service = buildService(fakeIdentity({ 'quotation.approve': [1, 2, 3], 'inventory.manage': [5], 'inventory.issue': [5, 6] }, identityCalls));
});

const quotation = { quotation_id: 10, quotation_number: 'QT-001', customer_name: 'Công ty A', total_amount: 1500000, currency: 'VND', created_by_id: 3 };

describe('NotificationService', () => {
  it('gửi việc cần duyệt cho mọi người có quyền, trừ người gửi', async () => {
    const result = await service.handleEvent(envelope('QuotationSubmitted', { ...quotation, actor: { id: 3, name: 'Sale' } }));

    assert.deepEqual(result, { created: 2, resolved: 0 });
    for (const userId of [1, 2]) {
      const [item] = repository.list(userId);
      assert.equal(item.category, 'task');
      assert.equal(item.title, 'Báo giá QT-001 cần phê duyệt');
      assert.equal(item.link, '/quotation/10');
      assert.equal(item.action_key, 'quotation:10:approve');
      assert.match(item.body, /Sale gửi duyệt báo giá cho Công ty A/);
    }
    assert.equal(repository.list(3).length, 0);
    assert.deepEqual(
      pushes.filter((p) => p.event === 'notification:new').map((p) => p.userId),
      [1, 2],
    );
    assert.ok(identityCalls[0].includes('permissions=quotation.approve'));
  });

  it('bỏ qua event trùng event_id (idempotent)', async () => {
    const event = envelope('QuotationSubmitted', { ...quotation, actor: { id: 3, name: 'Sale' } }, 'evt-dup');
    await service.handleEvent(event);
    const second = await service.handleEvent(event);

    assert.deepEqual(second, { skipped: 'DUPLICATE' });
    assert.equal(repository.list(1).length, 1);
  });

  it('một người duyệt xong thì việc cần làm của những người còn lại được đóng', async () => {
    await service.handleEvent(envelope('QuotationSubmitted', { ...quotation, actor: { id: 3, name: 'Sale' } }));
    pushes = [];

    const result = await service.handleEvent(envelope('QuotationApproved', { ...quotation, actor: { id: 1, name: 'Trưởng phòng' } }));

    assert.deepEqual(result, { created: 1, resolved: 2 });
    assert.equal(repository.list(2)[0].status, 'RESOLVED');
    assert.equal(repository.unreadCount(2), 0);
    const [info] = repository.list(3);
    assert.equal(info.title, 'Báo giá QT-001 đã được duyệt');
    assert.ok(pushes.some((p) => p.userId === 2 && p.event === 'notification:resolved'));
    assert.ok(pushes.some((p) => p.userId === 2 && p.event === 'notification:unread_count' && p.data.unread_count === 0));
  });

  it('xuất kho gửi người có quyền kho (inventory.manage / inventory.issue), không trùng người', async () => {
    await service.handleEvent(envelope('WarehouseDispatchRequested', { dispatch_id: 4, invoice_number: 'INV-1', warehouse_name: 'Kho HN', actor: { id: 9, name: 'KT' } }));

    assert.equal(repository.list(5).length, 1);
    assert.equal(repository.list(6).length, 1);
    assert.equal(repository.list(5)[0].action_key, 'dispatch:4:process');
  });

  it('nghỉ phép gửi đúng người duyệt trong payload rồi báo kết quả cho người làm đơn', async () => {
    const leave = { id: 7, code: 'NP-2026-0001', requester_id: 42, requester_name: 'Nhân viên', title: 'Nghỉ phép năm · 2 ngày' };
    await service.handleEvent(envelope('LeaveRequestSubmitted', { ...leave, approver: { id: 8, name: 'Quản lý' }, actor: { id: 42, name: 'Nhân viên' } }));
    await service.handleEvent(envelope('LeaveRequestRejected', { ...leave, comment: 'Cuối tháng bận', actor: { id: 8, name: 'Quản lý' } }));

    assert.equal(repository.list(8)[0].status, 'RESOLVED');
    const [result] = repository.list(42);
    assert.equal(result.title, 'Đơn nghỉ phép NP-2026-0001 bị từ chối');
    assert.match(result.body, /Lý do: Cuối tháng bận/);
    assert.equal(identityCalls.length, 0);
  });

  it('identity lỗi thì ném lỗi để retry, không đánh dấu đã xử lý', async () => {
    const failing = buildService(async () => ({ ok: false, status: 503 }));
    const event = envelope('QuotationSubmitted', { ...quotation, actor: { id: 3, name: 'Sale' } }, 'evt-retry');

    await assert.rejects(failing.handleEvent(event), /503/);
    assert.equal(repository.isProcessed('evt-retry'), false);

    const result = await service.handleEvent(event);
    assert.equal(result.created, 2);
  });

  it('event không có rule chỉ được đánh dấu đã xử lý', async () => {
    const result = await service.handleEvent(envelope('SomethingElse', {}, 'evt-none'));
    assert.deepEqual(result, { skipped: 'NO_RULE' });
    assert.equal(repository.isProcessed('evt-none'), true);
  });
});

describe('Inbox API', () => {
  async function withServer(fn) {
    const server = createServer(createApp({ repository, publisher: { toUser: (...args) => pushes.push(args) }, jwtSecret: SECRET }));
    server.listen(0);
    await once(server, 'listening');
    const base = `http://127.0.0.1:${server.address().port}/api/v1/notifications`;
    try {
      await fn(base);
    } finally {
      server.close();
    }
  }

  it('chỉ trả thông báo của người đang đăng nhập và đánh dấu đã đọc', async () => {
    await service.handleEvent(envelope('QuotationSubmitted', { ...quotation, actor: { id: 3, name: 'Sale' } }));
    const [mine] = repository.list(1);
    const [others] = repository.list(2);

    await withServer(async (base) => {
      assert.equal((await fetch(base)).status, 401);

      const auth = { Authorization: `Bearer ${token(1)}` };
      const list = await (await fetch(base, { headers: auth })).json();
      assert.equal(list.data.length, 1);
      assert.equal(list.meta.unread_count, 1);

      assert.equal((await fetch(`${base}/${others.id}/read`, { method: 'POST', headers: auth })).status, 404);

      const read = await (await fetch(`${base}/${mine.id}/read`, { method: 'POST', headers: auth })).json();
      assert.equal(read.data.status, 'READ');
      assert.equal(read.meta.unread_count, 0);

      const count = await (await fetch(`${base}/unread-count`, { headers: { Authorization: `Bearer ${token(2)}` } })).json();
      assert.equal(count.data.unread_count, 1);

      const all = await (await fetch(`${base}/read-all`, { method: 'POST', headers: { Authorization: `Bearer ${token(2)}` } })).json();
      assert.equal(all.data.updated, 1);
    });
  });
});
