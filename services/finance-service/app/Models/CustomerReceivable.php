<?php

namespace App\Models;

use Database\Factories\CustomerReceivableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerReceivable extends Model
{
    /** @use HasFactory<CustomerReceivableFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['invoice_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'original_amount' => 'float', 'paid_amount' => 'float', 'outstanding_amount' => 'float'];

    protected $appends = ['effective_status', 'overdue_days'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceivableAllocation::class, 'receivable_id');
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->outstanding_amount < 0) {
            return 'CREDIT';
        }
        if ($this->outstanding_amount == 0) {
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
        return $this->due_date && $this->outstanding_amount > 0 && $this->due_date->isBefore(today())
            ? (int) $this->due_date->diffInDays(today()) : 0;
    }
}
