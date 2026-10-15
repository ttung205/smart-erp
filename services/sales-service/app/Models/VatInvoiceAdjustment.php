<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VatInvoiceAdjustment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'adjustment_date' => 'date:Y-m-d',
        'subtotal_delta' => 'float', 'vat_delta' => 'float', 'total_delta' => 'float',
        'finance_synced_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(VatInvoice::class, 'original_vat_invoice_id');
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VatInvoiceAdjustmentItem::class);
    }

    public function issuedInvoice(): HasOne
    {
        return $this->hasOne(VatInvoice::class, 'adjustment_id');
    }
}
