<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $guarded = [];

    protected $casts = ['period_start' => 'date:Y-m-d', 'opening_days' => 'float'];
}
