<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PunchLog extends Model
{
    public const DEVICE_IMPORT = 'DEVICE_IMPORT';

    public const SIMULATOR = 'SIMULATOR';

    public const WEB = 'WEB';

    public const ADJUSTMENT = 'ADJUSTMENT';

    protected $guarded = [];

    protected $casts = ['punched_at' => 'datetime', 'work_date' => 'date:Y-m-d', 'voided_at' => 'datetime'];
}
