<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatInvoiceAdjustmentItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'original_quantity' => 'float', 'original_unit_price' => 'float',
        'original_vat_percent' => 'float', 'vat_percent_delta' => 'float', 'quantity_delta' => 'float',
        'unit_price_delta' => 'float', 'subtotal_delta' => 'float',
        'vat_delta' => 'float', 'total_delta' => 'float',
    ];

    public function originalItem(): BelongsTo
    {
        return $this->belongsTo(VatInvoiceItem::class, 'original_vat_invoice_item_id');
    }
}
