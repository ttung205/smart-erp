<?php

namespace App\Models;

use App\Support\IdentityUserResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturnRequest extends Model
{
    use HasFactory;

    public const PENDING_INSPECTION = 'PENDING_INSPECTION';

    public const INSPECTING = 'INSPECTING';

    public const REJECTED = 'REJECTED';

    public const TECHNICAL_RECEIVED = 'TECHNICAL_RECEIVED';

    public const PENDING_MAIN_APPROVAL = 'PENDING_MAIN_APPROVAL';

    public const MAIN_REJECTED = 'MAIN_REJECTED';

    public const PENDING_POSTING = 'PENDING_POSTING';

    public const COMPLETED = 'COMPLETED';

    protected $guarded = [];

    protected $casts = [
        'total_amount' => 'float',
        'inspection_started_at' => 'datetime',
        'inspected_at' => 'datetime',
        'technical_received_at' => 'datetime',
        'main_requested_at' => 'datetime',
        'main_approved_at' => 'datetime',
        'main_rejected_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function documents()
    {
        return $this->hasMany(SaleReturnRequestDocument::class);
    }

    public function items()
    {
        return $this->hasMany(SaleReturnRequestItem::class);
    }

    public function saleReturn()
    {
        return $this->hasOne(SaleReturn::class);
    }

    public function attachments()
    {
        return $this->hasMany(SalesAttachment::class, 'attachable_id')->where('attachable_type', 'sale_return_request');
    }

    public function getCreatedByNameAttribute(?string $value): string
    {
        return IdentityUserResolver::name($this->created_by_id, $value);
    }

    public function getInspectionStartedByNameAttribute(?string $value): string
    {
        return IdentityUserResolver::name($this->inspection_started_by_id, $value);
    }

    public function getInspectedByNameAttribute(?string $value): string
    {
        return IdentityUserResolver::name($this->inspected_by_id, $value);
    }

    public function getTechnicalReceivedByNameAttribute(?string $value): string
    {
        return IdentityUserResolver::name($this->technical_received_by_id, $value);
    }

    public function getMainRequestedByNameAttribute(?string $value): string
    {
        return IdentityUserResolver::name($this->main_requested_by_id, $value);
    }

    public function getMainApprovedByNameAttribute(?string $value): string
    {
        return IdentityUserResolver::name($this->main_approved_by_id, $value);
    }

    public function getMainRejectedByNameAttribute(?string $value): string
    {
        return IdentityUserResolver::name($this->main_rejected_by_id, $value);
    }

    public function getPostedByNameAttribute(?string $value): string
    {
        return IdentityUserResolver::name($this->posted_by_id, $value);
    }
}
