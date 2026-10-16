<?php

namespace App\Models;

use App\Contracts\Approvable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OfficeRequest extends Model implements Approvable
{
    public const PURCHASE = 'PURCHASE';

    public const PAYMENT = 'PAYMENT';

    public const DRAFT = 'DRAFT';

    public const PENDING = 'PENDING';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const RETURNED = 'RETURNED';

    public const CANCELLED = 'CANCELLED';

    protected $guarded = [];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OfficeRequestItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OfficeRequestAttachment::class);
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
        return in_array($this->status, [self::DRAFT, self::RETURNED], true);
    }

    public function approvalSummary(): array
    {
        return [
            'type' => 'office_request',
            'type_label' => $this->kind === self::PURCHASE ? 'Đề nghị mua sắm' : 'Đề nghị thanh toán',
            'id' => $this->id,
            'code' => $this->code,
            'kind' => $this->kind,
            'requester_id' => $this->user_id,
            'requester_name' => $this->user_name,
            'title' => $this->purpose,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'submitted_at' => $this->submitted_at,
        ];
    }
}
