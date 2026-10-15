<?php

namespace App\Models;

use App\Contracts\Approvable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LeaveRequest extends Model implements Approvable
{
    public const DRAFT = 'DRAFT';

    public const PENDING = 'PENDING';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const RETURNED = 'RETURNED';

    public const CANCELLED = 'CANCELLED';

    /** Trạng thái đang chiếm ngày nghỉ (dùng để kiểm tra trùng lịch). */
    public const ACTIVE_STATUSES = [self::PENDING, self::APPROVED];

    public const EDITABLE_STATUSES = [self::DRAFT, self::RETURNED];

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'total_days' => 'float', 'attachments' => 'array',
        'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvalSteps(): MorphMany
    {
        return $this->morphMany(ApprovalStep::class, 'approvable')->orderBy('step_order');
    }

    public function approvalHistories(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'approvable')->orderBy('id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function approvalSummary(): array
    {
        $this->loadMissing('leaveType');

        return [
            'type' => 'leave_request',
            'type_label' => 'Đơn xin nghỉ phép',
            'id' => $this->id,
            'code' => $this->code,
            'requester_id' => $this->user_id,
            'requester_name' => $this->user_name,
            'title' => sprintf('%s · %s ngày (%s → %s)', $this->leaveType?->name, rtrim(rtrim(number_format($this->total_days, 1, ',', '.'), '0'), ','),
                $this->start_date->format('d/m/Y'), $this->end_date->format('d/m/Y')),
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
        ];
    }
}
