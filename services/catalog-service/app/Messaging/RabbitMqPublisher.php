<?php

namespace App\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/** Publish message lên topic exchange erp.events với publisher confirm. */
class RabbitMqPublisher
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function publish(string $routingKey, string $body, string $messageId): void
    {
        $channel = $this->channel();
        $channel->basic_publish(new AMQPMessage($body, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $messageId,
            'app_id' => 'catalog-service',
        ]), (string) config('services.rabbitmq.exchange'), $routingKey, true);
        $channel->wait_for_pending_acks_returns(5);
    }

    public function close(): void
    {
        try {
            $this->channel?->close();
            $this->connection?->close();
        } catch (\Throwable) {
            // Kết nối đã đứt thì không cần đóng
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
            $config['host'], (int) $config['port'], $config['user'], $config['password'], $config['vhost'],
            connection_timeout: 3.0, read_write_timeout: 10.0, heartbeat: 0,
        );
        $this->channel = $this->connection->channel();
        $this->channel->confirm_select();
        // Không có queue nào bind routing key thì RabbitMQ trả message về: coi là lỗi để không mất event
        $this->channel->set_return_listener(function (): void {
            throw new UnroutableMessageException;
        });
        $this->channel->set_nack_handler(function (): void {
            throw new \RuntimeException('RabbitMQ từ chối message (nack).');
        });

        return $this->channel;
    }
}
