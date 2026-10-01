<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRequestItem extends Model
{
    protected $table = 'delivery_request_items';

    protected $fillable = [
        'delivery_request_id',
        'order_item_id',
        'sku',
        'quantity',
    ];

    protected $appends = [
        'delivery_date',
        'product_name',
        'ordered_quantity',
        'unit_price',
        'delivered_quantity',
    ];

    public function deliveryRequest()
    {
        return $this->belongsTo(DeliveryRequest::class, 'delivery_request_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(SalesOrderItem::class, 'order_item_id');
    }

    // Dynamic Accessors truy xuất trực tiếp từ SalesOrderItem qua order_item_id
    public function getDeliveryDateAttribute()
    {
        return $this->orderItem?->delivery_date 
            ?? $this->deliveryRequest?->expected_delivery_date 
            ?? '';
    }

    public function getProductNameAttribute()
    {
        return $this->orderItem?->product_name ?? $this->sku;
    }

    public function getOrderedQuantityAttribute()
    {
        return (float)($this->orderItem?->quantity ?? $this->quantity);
    }

    public function getUnitPriceAttribute()
    {
        return (float)($this->orderItem?->selling_price ?? $this->orderItem?->unit_price ?? 0);
    }

    public function getDeliveredQuantityAttribute()
    {
        return 0;
    }
}
