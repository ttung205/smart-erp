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
        'shipping_method',
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
        'inventory_reservation_status',
        'inventory_warehouse_id',
        'inventory_reservation_error',
        'inventory_reserved_at',
        'inventory_issue_status',
        'inventory_issue_error',
        'inventory_issued_at',
        'cancelled_at',
        'cancelled_by_id',
        'cancelled_by_name',
        'cancel_reason',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'total_amount' => 'float',
        'inventory_reserved_at' => 'datetime',
        'inventory_issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function attachments()
    {
        return $this->hasMany(SalesAttachment::class, 'attachable_id')->where('attachable_type', 'invoice');
    }

    public function deliveryRequest()
    {
        return $this->belongsTo(DeliveryRequest::class, 'delivery_request_id');
    }

    public function warehouseDispatch()
    {
        return $this->hasOne(WarehouseDispatch::class, 'invoice_id');
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

    // Dynamic Accessors từ warehouseDispatch
    public function getPickerIdAttribute()
    {
        return $this->warehouseDispatch?->picker_id;
    }

    public function getPackerIdAttribute()
    {
        return $this->warehouseDispatch?->packer_id;
    }

    public function getShipperIdAttribute()
    {
        return $this->warehouseDispatch?->shipper_id;
    }

    public function getPickerNameAttribute()
    {
        return $this->warehouseDispatch?->picker_name ?: ($this->attributes['picker_name'] ?? null);
    }

    public function getPackerNameAttribute()
    {
        return $this->warehouseDispatch?->packer_name ?: ($this->attributes['packer_name'] ?? null);
    }

    public function getShippingMethodAttribute()
    {
        return $this->warehouseDispatch?->shipping_method ?: ($this->attributes['shipping_method'] ?? 'CUSTOMER_PICKUP');
    }

    public function getShipperNameAttribute()
    {
        if ($this->warehouseDispatch) {
            return $this->warehouseDispatch->shipper_display_name;
        }

        return $this->attributes['shipper_name'] ?? null;
    }

    public function getShippingNoteAttribute()
    {
        return $this->warehouseDispatch?->shipping_note ?: ($this->attributes['shipping_note'] ?? null);
    }
}
