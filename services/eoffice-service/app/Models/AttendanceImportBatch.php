<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceImportBatch extends Model
{
    protected $guarded = [];

    protected $casts = ['errors' => 'array'];
}
