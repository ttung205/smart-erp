<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    public const PENDING = 'PENDING';

    public const PUBLISHED = 'PUBLISHED';

    public const FAILED = 'FAILED';

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
        'retry_count' => 'integer',
        'published_at' => 'datetime',
    ];
}
