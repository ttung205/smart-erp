<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $table = 'quotation_items';

    protected $fillable = [
        'quotation_id',
        'product_id',
        'sku',
        'description',
        'quantity',
        'gross_price',
        'discount_percent',
        'selling_price',
        'vat_percent',
        'lead_time',
        'subtotal',
        'won_quantity',
        'status',
    ];

    protected $casts = [
        'quantity' => 'float',
        'gross_price' => 'float',
        'discount_percent' => 'float',
        'selling_price' => 'float',
        'vat_percent' => 'float',
        'subtotal' => 'float',
        'won_quantity' => 'float',
    ];

    protected $appends = [
        'is_won',
        'designation',
        'brand',
        'com',
        'unit',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getGrossPriceAttribute()
    {
        $val = (float)($this->attributes['gross_price'] ?? 0);
        return $val > 0 ? $val : (float)($this->product?->gross_price ?? 0);
    }

    public function getIsWonAttribute()
    {
        return ($this->status === 'WIN' || $this->status === 'WON' || (float)$this->won_quantity > 0);
    }

    public function getDesignationAttribute()
    {
        return $this->product?->designation ?? '';
    }

    public function getBrandAttribute()
    {
        return $this->product?->brand?->name ?? '';
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
