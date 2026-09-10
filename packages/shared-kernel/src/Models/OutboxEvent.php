<?php

declare(strict_types=1);

namespace SmartERP\SharedKernel\Models;

use Illuminate\Database\Eloquent\Model;
use SmartERP\SharedKernel\Contracts\DomainEventInterface;

class OutboxEvent extends Model
{
    protected $table = 'outbox_events';

    protected $fillable = [
        'event_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'routing_key',
        'payload',
        'status',
        'retry_count',
        'last_error',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'retry_count' => 'integer',
        'published_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_FAILED = 'FAILED';

    public static function fromDomainEvent(DomainEventInterface $event, string $routingKey): self
    {
        return new self([
            'event_id' => $event->getEventId(),
            'event_type' => $event->getEventType(),
            'aggregate_type' => $event->getAggregateType(),
            'aggregate_id' => $event->getAggregateId(),
            'routing_key' => $routingKey,
            'payload' => $event->toArray(),
            'status' => self::STATUS_PENDING,
            'retry_count' => 0,
        ]);
    }
}
