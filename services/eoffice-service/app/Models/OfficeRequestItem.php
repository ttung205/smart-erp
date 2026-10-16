<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeRequestItem extends Model
{
    protected $guarded = [];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];
}
