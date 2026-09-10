<?php

declare(strict_types=1);

namespace SmartERP\SharedKernel\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedEvent extends Model
{
    protected $table = 'processed_events';

    protected $fillable = [
        'event_id',
        'event_type',
        'consumer_name',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public static function markProcessed(string $eventId, string $eventType, string $consumerName): self
    {
        return self::create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'consumer_name' => $consumerName,
            'processed_at' => now(),
        ]);
    }

    public static function isAlreadyProcessed(string $eventId, string $consumerName): bool
    {
        return self::where('event_id', $eventId)
            ->where('consumer_name', $consumerName)
            ->exists();
    }
}
