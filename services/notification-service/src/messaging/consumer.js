import amqp from 'amqplib';

/** Routing key notification-service quan tâm (khớp infrastructure/rabbitmq/definitions.json). */
export const BINDINGS = [
  'sales.quotation.*',
  'sales.order.submitted',
  'sales.order.approved',
  'sales.order.rejected',
  'sales.delivery.*',
  'sales.dispatch.*',
  'eoffice.leave.*',
  'catalog.product.*',
  'inventory.stock.*',
];

const RETRY_HEADER = 'x-retry-count';

/**
 * Consumer queue erp.notification.events.
 * - Thành công → ack.
 * - Lỗi tạm thời → đẩy sang erp.notification.retry (TTL) rồi tự quay lại queue chính, tối đa maxRetries lần.
 * - Quá số lần / message hỏng → nack không requeue → erp.dlx → erp.dead_letters.
 * Mất kết nối thì tự kết nối lại; service HTTP vẫn chạy khi broker chưa sẵn sàng.
 */
export function startConsumer({ rabbitmq, handle, logger = console }) {
  let stopped = false;
  let connection = null;
  let reconnectTimer = null;

  function scheduleReconnect(reason) {
    if (stopped || reconnectTimer) return;
    logger.warn(`[AMQP] ${reason}, thử lại sau 5s`);
    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      connectLoop();
    }, 5000);
  }

  async function setup() {
    const conn = await amqp.connect(rabbitmq.url);
    connection = conn;
    conn.on('error', (error) => logger.error('[AMQP] Lỗi kết nối:', error.message));
    conn.on('close', () => scheduleReconnect('Mất kết nối RabbitMQ'));

    const channel = await conn.createChannel();
    // Channel bị broker đóng (vd lỗi khai báo queue) thì đóng luôn connection để vòng reconnect dựng lại từ đầu
    channel.on('close', () => {
      if (!stopped) conn.close().catch(() => {});
    });
    await channel.assertExchange(rabbitmq.exchange, 'topic', { durable: true });
    await channel.assertExchange(rabbitmq.deadLetterExchange, 'topic', { durable: true });
    await channel.assertQueue(rabbitmq.queue, {
      durable: true,
      arguments: { 'x-dead-letter-exchange': rabbitmq.deadLetterExchange, 'x-dead-letter-routing-key': 'dead.letter.notification' },
    });
    await channel.assertQueue(rabbitmq.retryQueue, {
      durable: true,
      arguments: { 'x-message-ttl': rabbitmq.retryDelayMs, 'x-dead-letter-exchange': '', 'x-dead-letter-routing-key': rabbitmq.queue },
    });
    for (const key of BINDINGS) {
      await channel.bindQueue(rabbitmq.queue, rabbitmq.exchange, key);
    }
    await channel.prefetch(rabbitmq.prefetch);

    await channel.consume(rabbitmq.queue, async (message) => {
      if (!message) return;
      let envelope;
      try {
        envelope = JSON.parse(message.content.toString('utf8'));
      } catch {
        logger.error('[AMQP] Message không phải JSON, chuyển vào dead letter');
        channel.nack(message, false, false);
        return;
      }

      try {
        const result = await handle(envelope);
        logger.log(`[AMQP] ${envelope.event_type} (${envelope.event_id}) →`, result);
        channel.ack(message);
      } catch (error) {
        const attempt = Number(message.properties.headers?.[RETRY_HEADER] || 0) + 1;
        if (attempt > rabbitmq.maxRetries) {
          logger.error(`[AMQP] ${envelope.event_type} thất bại ${attempt - 1} lần, chuyển vào dead letter:`, error.message);
          channel.nack(message, false, false);
          return;
        }
        logger.warn(`[AMQP] ${envelope.event_type} lỗi (lần ${attempt}/${rabbitmq.maxRetries}), retry sau ${rabbitmq.retryDelayMs}ms:`, error.message);
        channel.sendToQueue(rabbitmq.retryQueue, message.content, {
          persistent: true,
          contentType: 'application/json',
          messageId: message.properties.messageId,
          headers: { ...message.properties.headers, [RETRY_HEADER]: attempt },
        });
        channel.ack(message);
      }
    });

    logger.log(`[AMQP] Đang lắng nghe queue ${rabbitmq.queue}`);
  }

  async function connectLoop() {
    if (stopped) return;
    try {
      await setup();
    } catch (error) {
      await connection?.close().catch(() => {});
      scheduleReconnect(`Chưa kết nối được RabbitMQ (${error.message})`);
    }
  }

  connectLoop();

  return {
    async stop() {
      stopped = true;
      clearTimeout(reconnectTimer);
      await connection?.close().catch(() => {});
    },
  };
}
