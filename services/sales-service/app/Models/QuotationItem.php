<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $table = 'quotation_items';

    protected $fillable = [
        'quotation_id',
        'sku',
        'designation',
        'brand',
        'description',
        'com',
        'unit',
        'quantity',
        'gross_price',
        'discount_percent',
        'selling_price',
        'vat_percent',
        'lead_time',
        'subtotal',
        'is_won',
        'won_quantity',
    ];

    protected $casts = [
        'is_won' => 'boolean',
        'quantity' => 'float',
        'gross_price' => 'float',
        'discount_percent' => 'float',
        'selling_price' => 'float',
        'vat_percent' => 'float',
        'subtotal' => 'float',
        'won_quantity' => 'float',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }
}
