<?php

namespace App\Models;

use Database\Factories\CashFundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFund extends Model
{
    /** @use HasFactory<CashFundFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['opening_balance' => 'float', 'is_active' => 'boolean'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }
}
