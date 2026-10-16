<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeRequestAttachment extends Model
{
    protected $guarded = [];

    protected $hidden = ['disk_path'];
}
