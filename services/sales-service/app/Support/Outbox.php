<?php

namespace App\Support;

use App\Models\OutboxEvent;
use Illuminate\Support\Str;

/**
 * Ghi domain event vào outbox_events theo envelope chuẩn (docs/contracts/events-spec.md).
 * Gọi bên trong DB::transaction của nghiệp vụ để event chỉ tồn tại khi dữ liệu đã commit.
 */
class Outbox
{
    public const PRODUCER = 'sales-service';

    /** @param  array<string, mixed>  $payload */
    public static function record(string $eventType, string $routingKey, string $aggregateType, int|string $aggregateId, array $payload): OutboxEvent
    {
        $eventId = (string) Str::uuid();

        return OutboxEvent::create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'routing_key' => $routingKey,
            'payload' => json_encode([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => (string) $aggregateId,
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'version' => '1.0',
                'correlation_id' => self::correlationId(),
                'producer' => self::PRODUCER,
                'payload' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'status' => OutboxEvent::PENDING,
        ]);
    }

    /** @return array{id: int, name: string}|null */
    public static function actor(?object $actor): ?array
    {
        return $actor ? ['id' => (int) $actor->id, 'name' => (string) $actor->name] : null;
    }

    private static function correlationId(): string
    {
        // Chạy từ console thì request rỗng, tự sinh correlation mới
        return request()->header('X-Correlation-ID') ?: 'corr_'.Str::uuid();
    }
}
