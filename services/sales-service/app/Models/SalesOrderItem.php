<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $table = 'sales_order_items';

    protected $fillable = [
        'order_id',
        'sku',
        'product_name',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }
}
