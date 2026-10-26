<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceAuditLog extends Model
{
    protected $guarded = [];

    protected $casts = ['work_date' => 'date:Y-m-d', 'before' => 'array', 'after' => 'array'];
}
