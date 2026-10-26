<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSlip extends Model
{
    protected $guarded = [];

    protected $casts = [
        'base_salary' => 'float', 'fixed_allowance' => 'float', 'standard_days' => 'float', 'paid_days' => 'float',
        'unpaid_days' => 'float', 'ot_hours_weekday' => 'float', 'ot_hours_weekend' => 'float', 'ot_hours_holiday' => 'float',
        'salary_amount' => 'float', 'allowance_amount' => 'float', 'ot_amount' => 'float', 'bonus_amount' => 'float',
        'gross_income' => 'float', 'insurance_amount' => 'float', 'taxable_income' => 'float', 'pit_amount' => 'float',
        'penalty_amount' => 'float', 'deduction_amount' => 'float', 'net_pay' => 'float', 'breakdown' => 'array',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
