<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_paid' => 'boolean', 'deducts_balance' => 'boolean', 'requires_attachment' => 'boolean',
        'is_active' => 'boolean', 'max_days_per_request' => 'float',
    ];
}
