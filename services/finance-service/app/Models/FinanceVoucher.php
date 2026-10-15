<?php

namespace App\Models;

use Database\Factories\FinanceVoucherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceVoucher extends Model
{
    /** @use HasFactory<FinanceVoucherFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['voucher_date' => 'date:Y-m-d', 'total_amount' => 'float', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(FinanceVoucherLine::class, 'voucher_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceivableAllocation::class, 'voucher_id');
    }

    public function cashFund(): BelongsTo
    {
        return $this->belongsTo(CashFund::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function destinationBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'destination_bank_account_id');
    }
}
