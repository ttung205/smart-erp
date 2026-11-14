import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { rules } from '../src/notifications/rules.js';

describe('Thông báo sản phẩm mới (ProductCreated)', () => {
  it('mở Bảng Tồn Kho và lọc theo Internal Code của sản phẩm', () => {
    const payload = { product_id: 923, sku: 'ZZ-DEMO-OUTBOX-1114-AOG', name: 'ZZ-DEMO-OUTBOX-1114' };
    assert.equal(rules.ProductCreated.link(payload), '/inventory/stocks?search=ZZ-DEMO-OUTBOX-1114-AOG');
  });

  it('mã có ký tự đặc biệt được mã hóa để không làm hỏng URL', () => {
    const link = rules.ProductCreated.link({ sku: 'A/B C&D-XYZ' });
    assert.equal(link, '/inventory/stocks?search=A%2FB%20C%26D-XYZ');
    assert.equal(new URL(link, 'http://x').searchParams.get('search'), 'A/B C&D-XYZ');
  });

  it('thiếu mã thì vẫn mở Bảng Tồn Kho (không còn trang Tổng Quan Kho)', () => {
    assert.equal(rules.ProductCreated.link({}), '/inventory/stocks');
  });

  it('vẫn gửi cho người có quyền inventory.view', () => {
    assert.deepEqual(rules.ProductCreated.recipients({}), { permissions: ['inventory.view'] });
  });
});
