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
        'designation',
        'brand',
        'com',
        'discount_percent',
        'gross_price',
        'selling_price',
        'vat_percent',
        'lead_time',
        'delivery_date',
        'order_quantity',
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

    public function getUnitAttribute()
    {
        return $this->orderItem?->unit ?? 'Cái';
    }

    public function getSubtotalAttribute()
    {
        return (float)($this->quantity * ($this->unit_price ?: ($this->orderItem?->selling_price ?? 0)));
    }

    public function getDesignationAttribute()
    {
        return $this->orderItem?->designation ?? '';
    }

    public function getBrandAttribute()
    {
        return $this->orderItem?->brand ?? '';
    }

    public function getComAttribute()
    {
        return $this->orderItem?->com ?? 'Việt Nam';
    }

    public function getDiscountPercentAttribute()
    {
        return (float)($this->orderItem?->discount_percent ?? 0);
    }

    public function getGrossPriceAttribute()
    {
        return (float)($this->orderItem?->gross_price ?? $this->unit_price);
    }

    public function getSellingPriceAttribute()
    {
        return (float)($this->unit_price ?: ($this->orderItem?->selling_price ?? 0));
    }

    public function getVatPercentAttribute()
    {
        return (float)($this->orderItem?->vat_percent ?? 0);
    }

    public function getLeadTimeAttribute()
    {
        return $this->orderItem?->lead_time ?? '';
    }

    public function getDeliveryDateAttribute()
    {
        return $this->orderItem?->delivery_date ?? '';
    }

    public function getOrderQuantityAttribute()
    {
        return (float)($this->orderItem?->quantity ?? 0);
    }
}
