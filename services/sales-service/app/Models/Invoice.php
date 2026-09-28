<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'delivery_request_number',
        'order_number',
        'customer_name',
        'warehouse_name',
        'invoice_date',
        'payment_terms',
        'total_amount',
        'warehouse_status',
        'accounting_status',
        'posted_at',
        'posted_by',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }
}
