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
        'designation',
        'brand',
        'description',
        'com',
        'stock',
        'unit',
        'quantity',
        'unit_price',
        'gross_price',
        'discount_percent',
        'selling_price',
        'vat_percent',
        'lead_time',
        'subtotal',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }
}
