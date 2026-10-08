<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReportItem extends Model
{
    use HasFactory;

    protected $table = 'sale_report_items';

    protected $fillable = [
        'order_id',
        'order_number',
        'order_item_id',
        'product_id',
        'sku',
        'product_name',
        'quantity',
        'delivered_quantity',
        'remaining_quantity',
        'selling_price',
        'vat',
        'vat_percent',
        'vat_amount',
        'subtotal',
        'total_amount',
        'delivery_date',
        'customer_id',
        'salesperson_id',
        'status',
    ];

    protected $casts = [
        'quantity' => 'float',
        'delivered_quantity' => 'float',
        'remaining_quantity' => 'float',
        'selling_price' => 'float',
        'vat' => 'float',
        'vat_percent' => 'float',
        'vat_amount' => 'float',
        'subtotal' => 'float',
        'total_amount' => 'float',
        'customer_id' => 'integer',
        'salesperson_id' => 'integer',
    ];

    protected $appends = [
        'customer_name',
        'salesperson_name',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(SalesOrderItem::class, 'order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function getCustomerNameAttribute()
    {
        return $this->customer?->name ?? ($this->order?->customer_name ?? '');
    }

    public function getSalespersonNameAttribute()
    {
        return $this->salesperson?->name ?? ($this->order?->created_by_name ?? '');
    }
}
