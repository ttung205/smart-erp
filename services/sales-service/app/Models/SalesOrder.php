<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $table = 'sales_orders';

    protected $fillable = [
        'order_number',
        'customer_order_number',
        'quotation_id',
        'customer_id',
        'customer_code',
        'customer_name',
        'customer_tax_code',
        'customer_address',
        'customer_phone',
        'customer_email',
        'contact_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'order_date',
        'currency',
        'exchange_rate',
        'created_by',
        'created_by_name',
        'assigned_to',
        'assigned_to_name',
        'approved_by',
        'approved_by_name',
        'approved_at',
        'subtotal',
        'vat_total',
        'total_amount',
        'status',
        'notes',
        'shipping_address',
    ];

    // customer/contact/creator/assignee/approver: object dựng từ snapshot, giữ nguyên hình dạng JSON cũ cho frontend
    protected $appends = [
        'quotation_number',
        'customer',
        'contact',
        'creator',
        'assignee',
        'approver',
    ];

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class, 'order_id');
    }

    public function attachments()
    {
        return $this->hasMany(SalesAttachment::class, 'attachable_id')->where('attachable_type', 'sales_order');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function getCustomerAttribute(): array
    {
        return [
            'id' => $this->customer_id,
            'code' => $this->customer_code,
            'name' => $this->customer_name,
            'tax_code' => $this->customer_tax_code,
            'address' => $this->customer_address,
            'phone' => $this->customer_phone,
            'email' => $this->customer_email,
        ];
    }

    public function getContactAttribute(): ?array
    {
        return $this->contact_id
            ? ['id' => $this->contact_id, 'name' => $this->contact_name, 'phone' => $this->contact_phone, 'email' => $this->contact_email]
            : null;
    }

    public function getCreatorAttribute(): ?array
    {
        return $this->created_by ? ['id' => (int) $this->created_by, 'name' => $this->created_by_name] : null;
    }

    public function getAssigneeAttribute(): ?array
    {
        return $this->assigned_to ? ['id' => (int) $this->assigned_to, 'name' => $this->assigned_to_name] : null;
    }

    public function getApproverAttribute(): ?array
    {
        return $this->approved_by ? ['id' => (int) $this->approved_by, 'name' => $this->approved_by_name] : null;
    }

    public function getQuotationNumberAttribute()
    {
        return $this->quotation?->quotation_number ?? '';
    }

    public function getCreatedByNameAttribute(): string
    {
        return $this->attributes['created_by_name'] ?? (string) $this->created_by;
    }

    public function getAssignedToNameAttribute(): string
    {
        return $this->attributes['assigned_to_name'] ?? (string) $this->assigned_to;
    }

    public function getApprovedByNameAttribute(): string
    {
        return $this->attributes['approved_by_name'] ?? (string) $this->approved_by;
    }
}
