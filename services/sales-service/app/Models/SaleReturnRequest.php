<?php

namespace App\Models;

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
}
