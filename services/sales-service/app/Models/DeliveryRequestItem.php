<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRequestItem extends Model
{
    protected $table = 'delivery_request_items';

    protected $fillable = [
        'delivery_request_id',
        'order_item_id',
        'product_id',
        'sku',
        'quantity',
        'delivered_quantity',
    ];

    protected $appends = [
        'delivery_date',
        'product_name',
        'ordered_quantity',
        'unit_price',
    ];

    public function deliveryRequest()
    {
        return $this->belongsTo(DeliveryRequest::class, 'delivery_request_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(SalesOrderItem::class, 'order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Dynamic Accessors truy xuất trực tiếp từ SalesOrderItem qua order_item_id
    public function getDeliveryDateAttribute()
    {
        if ($this->orderItem?->delivery_date) {
            return $this->orderItem->delivery_date;
        }

        // Không lazy-load quan hệ deliveryRequest: khi serialize, DeliveryRequest.delivery_date lại đọc items
        // → items lại kèm deliveryRequest... tạo vòng lặp JSON vô hạn. Chỉ lấy đúng giá trị ngày.
        $expected = $this->relationLoaded('deliveryRequest')
            ? $this->deliveryRequest?->expected_delivery_date
            : $this->deliveryRequest()->value('expected_delivery_date');

        return $expected ?? '';
    }

    public function getProductNameAttribute()
    {
        return $this->product?->designation ?? $this->orderItem?->product_name ?? $this->sku;
    }

    public function getOrderedQuantityAttribute()
    {
        return (float) ($this->orderItem?->quantity ?? $this->quantity);
    }

    public function getUnitPriceAttribute()
    {
        return (float) ($this->orderItem?->selling_price ?? $this->orderItem?->unit_price ?? 0);
    }
}
