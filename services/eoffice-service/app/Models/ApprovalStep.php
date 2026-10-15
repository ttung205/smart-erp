<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalStep extends Model
{
    public const PENDING = 'PENDING';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const RETURNED = 'RETURNED';

    public const CANCELLED = 'CANCELLED';

    protected $guarded = [];

    protected $casts = ['acted_at' => 'datetime'];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }
}
