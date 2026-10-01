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
        'delivery_date',
        'quantity',
        'gross_price',
        'discount_percent',
        'selling_price',
        'vat_percent',
        'lead_time',
        'subtotal',
        'status',
    ];

    protected $appends = [
        'designation',
        'brand',
        'description',
        'com',
        'unit',
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

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function deliveryRequestItems()
    {
        return $this->hasMany(DeliveryRequestItem::class, 'order_item_id');
    }

    public function getGrossPriceAttribute()
    {
        $val = (float)($this->attributes['gross_price'] ?? 0);
        return $val > 0 ? $val : (float)($this->product?->gross_price ?? 0);
    }

    public function getUnitPriceAttribute()
    {
        return (float)($this->selling_price ?? 0);
    }

    // Dynamic Accessors tải trực tiếp từ Catalog Product qua SKU
    public function getDesignationAttribute()
    {
        return $this->product?->designation ?? '';
    }

    public function getBrandAttribute()
    {
        return $this->product?->brand?->name ?? '';
    }

    public function getDescriptionAttribute()
    {
        return $this->product?->description ?? $this->product_name ?? '';
    }

    public function getComAttribute()
    {
        return $this->product?->com ?? 'Việt Nam';
    }

    public function getUnitAttribute()
    {
        return $this->product?->unit ?? 'Cái';
    }
}
