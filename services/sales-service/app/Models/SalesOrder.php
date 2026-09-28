<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $table = 'sales_orders';

    protected $fillable = [
        'order_number',
        'quotation_number',
        'customer_id',
        'customer_name',
        'order_date',
        'total_amount',
        'status',
        'notes',
    ];

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class, 'order_id');
    }
}
