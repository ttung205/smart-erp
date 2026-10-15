<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReceivable extends Model
{
    use HasFactory;

    protected $fillable = [
        'vat_invoice_id', 'customer_id', 'customer_name', 'customer_tax_code',
        'invoice_number', 'invoice_series', 'invoice_date', 'due_date', 'currency',
        'original_amount', 'paid_amount', 'outstanding_amount', 'status',
    ];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'original_amount' => 'float',
        'paid_amount' => 'float',
        'outstanding_amount' => 'float',
    ];

    protected $appends = ['effective_status', 'overdue_days'];

    public function vatInvoice(): BelongsTo
    {
        return $this->belongsTo(VatInvoice::class);
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->outstanding_amount <= 0) {
            return 'PAID';
        }
        if ($this->paid_amount > 0) {
            return 'PARTIALLY_PAID';
        }
        if ($this->due_date?->isBefore(today())) {
            return 'OVERDUE';
        }

        return 'OPEN';
    }

    public function getOverdueDaysAttribute(): int
    {
        if (! $this->due_date || $this->outstanding_amount <= 0 || ! $this->due_date->isBefore(today())) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(today());
    }
}
