<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['quantity' => 'float', 'unit_price' => 'float', 'line_total' => 'float'];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }
}
