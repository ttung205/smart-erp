<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRequest extends Model
{
    protected $table = 'delivery_requests';

    protected $fillable = [
        'order_id',
        'request_number',
        'warehouse_name',
        'shipping_address',
        'contact_phone',
        'request_date',
        'expected_delivery_date',
        'status',
    ];

    protected $appends = [
        'order_number',
        'customer_order_number',
        'customer_name',
        'salesperson',
        'delivery_date',
    ];

    public function items()
    {
        return $this->hasMany(DeliveryRequestItem::class, 'delivery_request_id');
    }

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    // Dynamic Accessors join trực tiếp từ Order gốc theo order_id chuẩn quan hệ
    public function getOrderNumberAttribute()
    {
        return $this->order?->order_number ?? '';
    }

    public function getCustomerOrderNumberAttribute()
    {
        return $this->order?->customer_order_number ?? $this->order?->order_number ?? '';
    }

    public function getCustomerNameAttribute()
    {
        return $this->order?->customer_name ?? '';
    }

    public function getSalespersonAttribute()
    {
        return $this->order?->created_by_name ?? 'Nhân viên KD';
    }

    public function getDeliveryDateAttribute()
    {
        return $this->expected_delivery_date ?: ($this->items->first()?->delivery_date ?? '');
    }
}
