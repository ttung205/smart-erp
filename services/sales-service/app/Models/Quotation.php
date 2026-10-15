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
        'contact_id',
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

    protected $appends = [
        'customer_code',
        'contact_name',
    ];

    public function items()
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }

    public function attachments()
    {
        return $this->hasMany(SalesAttachment::class, 'attachable_id')->where('attachable_type', 'quotation');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function contact()
    {
        return $this->belongsTo(CustomerContact::class, 'contact_id');
    }

    // Dynamic Accessors từ Catalog Service
    public function getCustomerCodeAttribute()
    {
        return $this->customer?->code ?? '';
    }

    public function getContactNameAttribute()
    {
        return $this->contact?->name ?? '';
    }
}
