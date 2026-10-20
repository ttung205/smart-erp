<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $table = 'quotations';

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'customer_name',
        'customer_code',
        'customer_tax_code',
        'customer_address',
        'customer_phone',
        'customer_email',
        'contact_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'quotation_date',
        'currency',
        'exchange_rate',
        'created_by',
        'created_by_id',
        'assigned_to',
        'status',
        'approved_by',
        'approved_at',
        'subtotal',
        'vat_total',
        'total_amount',
        'notes',
    ];

    // customer/contact: object dựng từ snapshot, giữ nguyên hình dạng JSON cũ cho frontend (in PDF báo giá)
    protected $appends = [
        'customer',
        'contact',
    ];

    public function items()
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }

    public function attachments()
    {
        return $this->hasMany(SalesAttachment::class, 'attachable_id')->where('attachable_type', 'quotation');
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
            'contact_name' => $this->contact_name,
        ];
    }

    public function getContactAttribute(): ?array
    {
        return $this->contact_id
            ? ['id' => $this->contact_id, 'name' => $this->contact_name, 'phone' => $this->contact_phone, 'email' => $this->contact_email]
            : null;
    }
}
