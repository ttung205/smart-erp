import amqp from 'amqplib';

const RETRY_HEADER = 'x-retry-count';

export async function enqueueEmail(rabbitmq, job) {
  const connection = await amqp.connect(rabbitmq.url);
  try {
    const channel = await connection.createConfirmChannel();
    await channel.assertQueue(rabbitmq.emailQueue, {
      durable: true,
      arguments: { 'x-dead-letter-exchange': rabbitmq.deadLetterExchange, 'x-dead-letter-routing-key': 'dead.letter.email' },
    });
    channel.sendToQueue(rabbitmq.emailQueue, Buffer.from(JSON.stringify(job)), {
      persistent: true,
      contentType: 'application/json',
      messageId: job.id,
    });
    await channel.waitForConfirms();
  } finally {
    await connection.close().catch(() => { });
  }
}

export function startEmailConsumer({ rabbitmq, handle, onFailed, logger = console }) {
  let stopped = false;
  let connection = null;
  let reconnectTimer = null;

  function reconnect(reason) {
    if (stopped || reconnectTimer) return;
    logger.warn(`[Email AMQP] ${reason}; retry in 5s`);
    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      connect();
    }, 5000);
  }

  async function connect() {
    if (stopped) return;
    try {
      const conn = await amqp.connect(rabbitmq.url);
      connection = conn;
      conn.on('error', (error) => logger.error('[Email AMQP] Connection error:', error));
      conn.on('close', () => reconnect('Connection closed'));
      const channel = await conn.createConfirmChannel();
      channel.on('close', () => { if (!stopped) conn.close().catch(() => { }); });
      await channel.assertExchange(rabbitmq.deadLetterExchange, 'topic', { durable: true });
      await channel.assertQueue(rabbitmq.emailQueue, {
        durable: true,
        arguments: { 'x-dead-letter-exchange': rabbitmq.deadLetterExchange, 'x-dead-letter-routing-key': 'dead.letter.email' },
      });
      await channel.assertQueue(rabbitmq.emailRetryQueue, {
        durable: true,
        arguments: { 'x-message-ttl': rabbitmq.retryDelayMs, 'x-dead-letter-exchange': '', 'x-dead-letter-routing-key': rabbitmq.emailQueue },
      });
      await channel.prefetch(1);
      await channel.consume(rabbitmq.emailQueue, async (message) => {
        if (!message) return;
        let job;
        try {
          job = JSON.parse(message.content.toString('utf8'));
          if (!job?.id || !job?.mailOptions?.to || !job?.mailOptions?.subject) throw new Error('Invalid email job');
        } catch (error) {
          logger.error('[Email AMQP] Invalid job:', error);
          channel.nack(message, false, false);
          return;
        }
        try {
          await handle(job);
          channel.ack(message);
        } catch (error) {
          const attempt = Number(message.properties.headers?.[RETRY_HEADER] || 0) + 1;
          if (attempt > rabbitmq.maxRetries) {
            logger.error(`[Email AMQP] Job ${job.id} failed permanently:`, error);
            try { onFailed?.(job, error); } catch (logError) { logger.error('[Email AMQP] Failed to record error:', logError); }
            channel.nack(message, false, false);
            return;
          }
          try {
            channel.sendToQueue(rabbitmq.emailRetryQueue, message.content, {
              persistent: true,
              contentType: 'application/json',
              messageId: job.id,
              headers: { ...message.properties.headers, [RETRY_HEADER]: attempt },
            });
            await channel.waitForConfirms();
            channel.ack(message);
          } catch (publishError) {
            logger.error('[Email AMQP] Retry publish failed:', publishError);
            channel.nack(message, false, true);
          }
        }
      });
      logger.log(`[Email AMQP] Listening on ${rabbitmq.emailQueue}`);
    } catch (error) {
      await connection?.close().catch(() => { });
      reconnect(error.message);
    }
  }

  connect();
  return {
    async stop() {
      stopped = true;
      clearTimeout(reconnectTimer);
      await connection?.close().catch(() => { });
    },
  };
}
