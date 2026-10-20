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
        'designation',
        'brand',
        'com',
        'unit',
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
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function getIsWonAttribute()
    {
        return ($this->status === 'WIN' || $this->status === 'WON' || (float)$this->won_quantity > 0);
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
