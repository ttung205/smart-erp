<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRequestItem extends Model
{
    protected $table = 'delivery_request_items';

    protected $fillable = [
        'delivery_request_id',
        'sku',
        'product_name',
        'quantity',
        'unit_price',
    ];

    public function deliveryRequest()
    {
        return $this->belongsTo(DeliveryRequest::class, 'delivery_request_id');
    }
}
