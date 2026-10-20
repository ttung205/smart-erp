<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $table = 'sales_order_items';

    protected $fillable = [
        'order_id',
        'quotation_item_id',
        'product_id',
        'sku',
        'product_name',
        'designation',
        'brand',
        'description',
        'com',
        'unit',
        'delivery_date',
        'quantity',
        'delivered_quantity',
        'gross_price',
        'discount_percent',
        'selling_price',
        'vat_percent',
        'lead_time',
        'subtotal',
        'status',
    ];

    protected $appends = [
        'unit_price',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function quotationItem()
    {
        return $this->belongsTo(QuotationItem::class, 'quotation_item_id');
    }

    public function deliveryRequestItems()
    {
        return $this->hasMany(DeliveryRequestItem::class, 'order_item_id');
    }

    public function getUnitPriceAttribute()
    {
        return (float)($this->selling_price ?? 0);
    }

    public function getDescriptionAttribute(): string
    {
        return $this->attributes['description'] ?? $this->product_name ?? '';
    }

    public function getComAttribute(): string
    {
        return $this->attributes['com'] ?? 'Việt Nam';
    }

    public function getUnitAttribute(): string
    {
        return $this->attributes['unit'] ?? 'Cái';
    }
}
