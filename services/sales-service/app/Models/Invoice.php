<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'delivery_request_id',
        'delivery_request_number',
        'invoice_date',
        'currency',
        'exchange_rate',
        'created_by',
        'payment_terms',
        'total_amount',
        'notes',
        'picker_name',
        'packer_name',
        'shipper_name',
        'shipping_note',
        'warehouse_posted_at',
        'warehouse_posted_by',
        'warehouse_status',
        'accounting_status',
        'posted_at',
        'posted_by',
    ];

    protected $appends = [
        'order_id',
        'order_number',
        'customer_order_number',
        'customer_id',
        'customer_name',
        'warehouse_name',
        'shipping_address',
        'contact_phone',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function deliveryRequest()
    {
        return $this->belongsTo(DeliveryRequest::class, 'delivery_request_id');
    }

    // Dynamic Accessors tải trực tiếp từ DeliveryRequest và SalesOrder
    public function getOrderIdAttribute()
    {
        return $this->deliveryRequest?->order_id;
    }

    public function getOrderNumberAttribute()
    {
        return $this->deliveryRequest?->order?->order_number ?? '';
    }

    public function getCustomerOrderNumberAttribute()
    {
        return $this->deliveryRequest?->customer_order_number ?? '';
    }

    public function getCustomerIdAttribute()
    {
        return $this->deliveryRequest?->order?->customer_id;
    }

    public function getCustomerNameAttribute()
    {
        return $this->deliveryRequest?->customer_name ?? '';
    }

    public function getWarehouseNameAttribute()
    {
        return $this->deliveryRequest?->warehouse_name ?? 'Kho Tổng Miền Bắc (Hà Nội)';
    }

    public function getShippingAddressAttribute()
    {
        return $this->deliveryRequest?->shipping_address ?? '';
    }

    public function getContactPhoneAttribute()
    {
        return $this->deliveryRequest?->contact_phone ?? '';
    }
}
