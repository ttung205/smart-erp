<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PayrollPeriod extends Model
{
    public const DRAFT = 'DRAFT';

    public const CALCULATED = 'CALCULATED';

    public const PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const APPROVED = 'APPROVED';

    public const PAID = 'PAID';

    public const RETURNED = 'RETURNED';

    protected $guarded = [];

    protected $casts = [
        'standard_days' => 'float', 'rules_snapshot' => 'array',
        'total_gross' => 'float', 'total_insurance' => 'float', 'total_pit' => 'float', 'total_net' => 'float',
        'attendance_locked_at' => 'datetime', 'calculated_at' => 'datetime', 'submitted_at' => 'datetime',
        'decided_at' => 'datetime', 'paid_at' => 'datetime',
    ];

    public function slips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    public function approvalHistories(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'approvable')->orderBy('id');
    }

    /** Còn được tính lại / sửa dữ liệu đầu vào (chưa gửi duyệt). */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::DRAFT, self::CALCULATED, self::RETURNED], true);
    }

    public function label(): string
    {
        return sprintf('%02d/%d', $this->month, $this->year);
    }
}
