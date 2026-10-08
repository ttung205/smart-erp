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
        'contact_id',
        'order_date',
        'currency',
        'exchange_rate',
        'created_by',
        'assigned_to',
        'approved_by',
        'approved_at',
        'subtotal',
        'vat_total',
        'total_amount',
        'status',
        'notes',
        'shipping_address',
    ];

    protected $appends = [
        'customer_name',
        'customer_code',
        'contact_name',
        'quotation_number',
        'created_by_name',
        'assigned_to_name',
        'approved_by_name',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function contact()
    {
        return $this->belongsTo(CustomerContact::class, 'contact_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Accessors for seamless backward compatibility and UI display
    public function getCustomerNameAttribute()
    {
        return $this->customer?->name ?? '';
    }

    public function getCustomerCodeAttribute()
    {
        return $this->customer?->code ?? '';
    }

    public function getContactNameAttribute()
    {
        return $this->contact?->name ?? '';
    }

    public function getQuotationNumberAttribute()
    {
        return $this->quotation?->quotation_number ?? '';
    }

    public function getCreatedByNameAttribute()
    {
        return $this->creator?->name ?? (string)$this->created_by;
    }

    public function getAssignedToNameAttribute()
    {
        return $this->assignee?->name ?? (string)$this->assigned_to;
    }

    public function getApprovedByNameAttribute()
    {
        return $this->approver?->name ?? (string)$this->approved_by;
    }
}
