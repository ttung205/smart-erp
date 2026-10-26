<?php

namespace App\Models;

use App\Contracts\Approvable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AttendanceRequest extends Model implements Approvable
{
    public const DRAFT = 'DRAFT';

    public const PENDING = 'PENDING';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const RETURNED = 'RETURNED';

    public const CANCELLED = 'CANCELLED';

    public const ACTIVE_STATUSES = [self::PENDING, self::APPROVED];

    public const EDITABLE_STATUSES = [self::DRAFT, self::RETURNED];

    /** Quên chấm vào hoặc ra: bổ sung giờ còn thiếu. */
    public const ADD_PUNCH = 'ADD_PUNCH';

    /** Bù công: không có dữ liệu chấm công nhưng thực tế có đi làm. */
    public const MAKEUP = 'MAKEUP';

    public const REMOTE = 'REMOTE';

    public const BUSINESS_TRIP = 'BUSINESS_TRIP';

    public const OVERTIME = 'OVERTIME';

    public const TYPES = [self::ADD_PUNCH, self::MAKEUP, self::REMOTE, self::BUSINESS_TRIP, self::OVERTIME];

    public const TYPE_LABELS = [
        self::ADD_PUNCH => 'Bổ sung chấm công',
        self::MAKEUP => 'Bù công',
        self::REMOTE => 'Làm việc từ xa',
        self::BUSINESS_TRIP => 'Công tác',
        self::OVERTIME => 'Làm thêm giờ',
    ];

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d',
        'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

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

    /** @return list<string> các ngày (Y-m-d) mà yêu cầu này tác động */
    public function dates(): array
    {
        $dates = [];
        for ($day = $this->start_date->copy(); $day->lte($this->end_date); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    public function approvalSummary(): array
    {
        $range = $this->start_date->equalTo($this->end_date)
            ? $this->start_date->format('d/m/Y')
            : $this->start_date->format('d/m/Y').' → '.$this->end_date->format('d/m/Y');

        return [
            'type' => 'attendance_request',
            'type_label' => self::TYPE_LABELS[$this->type] ?? 'Yêu cầu chấm công',
            'id' => $this->id,
            'code' => $this->code,
            'requester_id' => $this->user_id,
            'requester_name' => $this->user_name,
            'title' => (self::TYPE_LABELS[$this->type] ?? 'Yêu cầu').' · '.$range,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
        ];
    }
}
