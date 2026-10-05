<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'order_item_id',
        'delivery_request_item_id',
        'product_id',
        'sku',
        'product_name',
        'description',
        'requested_quantity',
        'quantity',
        'delivered_quantity',
        'unit_price',
    ];

    protected $appends = [
        'unit',
        'subtotal',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(SalesOrderItem::class, 'order_item_id');
    }

    public function deliveryRequestItem()
    {
        return $this->belongsTo(DeliveryRequestItem::class, 'delivery_request_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getUnitAttribute()
    {
        return $this->orderItem?->unit ?? 'Cái';
    }

    public function getSubtotalAttribute()
    {
        return (float)($this->quantity * $this->unit_price);
    }
}
