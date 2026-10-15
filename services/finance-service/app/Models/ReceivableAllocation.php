<?php

namespace App\Models;

use Database\Factories\ReceivableAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableAllocation extends Model
{
    /** @use HasFactory<ReceivableAllocationFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['amount' => 'float', 'allocated_at' => 'datetime'];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FinanceVoucher::class);
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(CustomerReceivable::class);
    }
}
