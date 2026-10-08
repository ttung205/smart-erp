<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseDispatch extends Model
{
    protected $table = 'warehouse_dispatches';

    protected $fillable = [
        'invoice_id',
        'invoice_number',
        'delivery_request_id',
        'delivery_request_number',
        'order_id',
        'order_number',
        'customer_name',
        'warehouse_id',
        'warehouse_name',
        'status',
        'picker_id',
        'picker_name',
        'packer_id',
        'packer_name',
        'shipping_method',
        'shipper_id',
        'carrier_name',
        'shipping_note',
        'posted_at',
        'posted_by_id',
        'posted_by_name',
    ];

    protected $appends = [
        'picker_display_name',
        'packer_display_name',
        'shipper_display_name',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function deliveryRequest()
    {
        return $this->belongsTo(DeliveryRequest::class, 'delivery_request_id');
    }

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function picker()
    {
        return $this->belongsTo(User::class, 'picker_id');
    }

    public function packer()
    {
        return $this->belongsTo(User::class, 'packer_id');
    }

    public function shipper()
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by_id');
    }

    // Dynamic Display Names lấy từ join User hoặc snapshot
    public function getPickerDisplayNameAttribute()
    {
        return $this->picker?->name ?: ($this->picker_name ?: null);
    }

    public function getPackerDisplayNameAttribute()
    {
        return $this->packer?->name ?: ($this->packer_name ?: null);
    }

    public function getShipperDisplayNameAttribute()
    {
        if ($this->shipping_method === 'WAREHOUSE_DELIVERY') {
            return $this->shipper?->name ?: ($this->carrier_name ?: null);
        }
        return $this->carrier_name ?: ($this->shipper?->name ?: null);
    }
}
