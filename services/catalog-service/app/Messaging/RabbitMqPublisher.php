<?php

namespace App\Messaging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * AMQP Publisher cho catalog-service, publish domain events lên topic exchange 'erp.events'.
 */
class RabbitMqPublisher
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    /**
     * Publish một message lên topic exchange erp.events.
     */
    public function publish(string $routingKey, string $body, string $messageId): void
    {
        $channel = $this->channel();
        $channel->basic_publish(new AMQPMessage($body, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $messageId,
            'app_id' => 'catalog-service',
        ]), (string) config('services.rabbitmq.exchange', 'erp.events'), $routingKey);
    }

    /**
     * Publish sự kiện ProductCreated theo chuẩn CloudEvents envelope.
     *
     * @param array<string, mixed> $productData
     */
    public function publishProductCreated(array $productData): ?string
    {
        try {
            $eventId = 'evt_prod_' . (string) Str::uuid();
            $correlationId = request()->header('X-Correlation-ID') ?: 'corr_' . (string) Str::uuid();

            $envelope = [
                'event_id' => $eventId,
                'event_type' => 'ProductCreated',
                'aggregate_type' => 'Product',
                'aggregate_id' => (string) ($productData['product_id'] ?? $productData['id'] ?? ''),
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'version' => '1.0',
                'correlation_id' => $correlationId,
                'producer' => 'catalog-service',
                'payload' => [
                    'product_id' => (int) ($productData['product_id'] ?? $productData['id'] ?? 0),
                    'sku' => (string) ($productData['sku'] ?? ''),
                    'name' => (string) ($productData['designation'] ?? $productData['name'] ?? ''),
                    'origin' => (string) ($productData['com'] ?? $productData['origin'] ?? 'Việt Nam'),
                    'unit' => (string) ($productData['unit'] ?? 'Cái'),
                    'gross_price' => (float) ($productData['gross_price'] ?? 0),
                    'min_stock' => (int) ($productData['min_stock'] ?? 0),
                ],
            ];

            $body = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->publish('catalog.product.created', $body, $eventId);

            Log::info("[Catalog] Đã publish event ProductCreated ({$eventId}) cho SKU {$productData['sku']}");
            return $eventId;
        } catch (\Throwable $e) {
            Log::warning("[Catalog] Publish ProductCreated thất bại: " . $e->getMessage());
            $this->close();
            return null;
        }
    }

    public function close(): void
    {
        try {
            $this->channel?->close();
            $this->connection?->close();
        } catch (\Throwable) {
            // Đóng im lặng nếu kết nối đã đứt
        }
        $this->channel = null;
        $this->connection = null;
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel?->is_open()) {
            return $this->channel;
        }

        $config = config('services.rabbitmq');
        $this->connection = new AMQPStreamConnection(
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 5672),
            $config['user'] ?? 'erp_rabbit',
            $config['password'] ?? 'rabbit_secret',
            $config['vhost'] ?? '/',
            connection_timeout: 3.0,
            read_write_timeout: 10.0,
            heartbeat: 0
        );

        $this->channel = $this->connection->channel();
        return $this->channel;
    }
}
