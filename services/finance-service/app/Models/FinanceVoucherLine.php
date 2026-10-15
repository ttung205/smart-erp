<?php

namespace App\Models;

use Database\Factories\FinanceVoucherLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceVoucherLine extends Model
{
    /** @use HasFactory<FinanceVoucherLineFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['amount' => 'float'];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FinanceVoucher::class);
    }
}
