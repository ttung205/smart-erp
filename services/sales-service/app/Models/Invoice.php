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
        'payment_terms',
        'total_amount',
        'warehouse_status',
        'accounting_status',
        'posted_at',
        'posted_by',
    ];

    protected $appends = [
        'order_number',
        'customer_name',
        'warehouse_name',
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
    public function getOrderNumberAttribute()
    {
        return $this->deliveryRequest?->order?->order_number ?? '';
    }

    public function getCustomerNameAttribute()
    {
        return $this->deliveryRequest?->order?->customer_name ?? '';
    }

    public function getWarehouseNameAttribute()
    {
        return $this->deliveryRequest?->warehouse_name ?? 'Kho Tổng Miền Bắc (Hà Nội)';
    }
}
