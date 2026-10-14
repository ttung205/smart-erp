<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturnRequestItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'delivered_quantity' => 'float',
        'previously_returned_quantity' => 'float',
        'requested_quantity' => 'float',
        'accepted_quantity' => 'float',
        'rejected_quantity' => 'float',
        'unit_price' => 'float',
        'line_total' => 'float',
    ];

    public function request()
    {
        return $this->belongsTo(SaleReturnRequest::class, 'sale_return_request_id');
    }
}
