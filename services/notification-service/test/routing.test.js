import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import { BINDINGS } from '../src/messaging/consumer.js';
import { NotificationRepository } from '../src/notifications/notification.repository.js';
import { NotificationService } from '../src/notifications/notification.service.js';
import { rules } from '../src/notifications/rules.js';
import { openTestDatabase } from './helpers/mongo.js';

const definitions = JSON.parse(readFileSync(fileURLToPath(new URL('../../../infrastructure/rabbitmq/definitions.json', import.meta.url)), 'utf8'));

/** Khớp routing key theo quy tắc topic exchange của RabbitMQ (* = đúng 1 từ, # = 0..n từ). */
function topicMatches(pattern, key) {
  const walk = (p, k) => {
    if (!p.length) return !k.length;
    if (p[0] === '#') return walk(p.slice(1), k) || (k.length > 0 && walk(p, k.slice(1)));
    return k.length > 0 && (p[0] === '*' || p[0] === k[0]) && walk(p.slice(1), k.slice(1));
  };
  return walk(pattern.split('.'), key.split('.'));
}

function queuesFor(routingKey) {
  return definitions.bindings
    .filter((b) => b.source === 'erp.events' && topicMatches(b.routing_key, routingKey))
    .map((b) => b.destination);
}

describe('RabbitMQ routing: inventory.goods.issued', () => {
  it('chỉ route tới erp.notification.events (consumer thực sự của GoodsIssued)', () => {
    assert.deepEqual([...new Set(queuesFor('inventory.goods.issued'))], ['erp.notification.events']);
  });

  it('mọi binding của notification trong definitions.json khớp BINDINGS của consumer', () => {
    const declared = definitions.bindings.filter((b) => b.destination === 'erp.notification.events').map((b) => b.routing_key);
    assert.deepEqual([...declared].sort(), [...BINDINGS].sort());
  });

  it('không có binding tới queue không có consumer (sales, purchase, eoffice, analytics)', () => {
    for (const queue of ['erp.sales.events', 'erp.purchase.events', 'erp.eoffice.events', 'erp.analytics.events']) {
      assert.deepEqual(definitions.bindings.filter((b) => b.destination === queue), [], queue);
    }
  });
});

describe('GoodsIssued xử lý đúng một lần', () => {
  it('broadcast một lần dù message bị giao lại', async () => {
    const database = await openTestDatabase();
    const broadcasts = [];
    const service = new NotificationService({
      repository: new NotificationRepository(database),
      resolver: {},
      rules,
      publisher: { broadcast: (event, data) => broadcasts.push({ event, data }) },
      logger: { log() {}, warn() {}, error() {} },
    });
    const envelope = {
      event_id: 'evt-goods-issued-1',
      event_type: 'GoodsIssued',
      payload: { issue_id: 'PXK-1', warehouse_id: 1, items: [{ product_id: 7, quantity_issued: 2 }] },
    };

    assert.deepEqual(await service.handleEvent(envelope), { skipped: 'NO_RULE' });
    assert.deepEqual(await service.handleEvent(envelope), { skipped: 'DUPLICATE' });
    assert.equal(broadcasts.length, 1);
    assert.equal(broadcasts[0].event, 'inventory:stock_updated');
    assert.equal(broadcasts[0].data.issue_id, 'PXK-1');
    await database.drop();
    await database.close();
  });
});
