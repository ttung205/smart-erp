<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRequest extends Model
{
    protected $table = 'delivery_requests';

    protected $fillable = [
        'request_number',
        'order_number',
        'customer_name',
        'warehouse_name',
        'shipping_address',
        'contact_phone',
        'request_date',
        'expected_delivery_date',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(DeliveryRequestItem::class, 'delivery_request_id');
    }
}
