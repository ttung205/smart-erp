<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VatInvoice extends Model
{
    use HasFactory;

    protected $table = 'vat_invoices';

    protected $fillable = [
        'invoice_number',
        'invoice_series',
        'invoice_date',
        'sales_invoice_id',
        'sales_invoice_number',
        'order_id',
        'order_number',
        'customer_id',
        'customer_name',
        'customer_tax_code',
        'customer_address',
        'currency',
        'exchange_rate',
        'subtotal',
        'vat_total',
        'total_amount',
        'created_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'subtotal' => 'float',
        'vat_total' => 'float',
        'total_amount' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(VatInvoiceItem::class, 'vat_invoice_id');
    }

    public function salesInvoice()
    {
        return $this->belongsTo(Invoice::class, 'sales_invoice_id');
    }

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
