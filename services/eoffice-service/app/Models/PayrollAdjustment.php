<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollAdjustment extends Model
{
    public const BONUS = 'BONUS';

    public const DEDUCTION = 'DEDUCTION';

    protected $guarded = [];

    protected $casts = ['amount' => 'float', 'taxable' => 'boolean'];
}
