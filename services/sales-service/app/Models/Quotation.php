<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $table = 'quotations';

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'customer_code',
        'customer_name',
        'contact_id',
        'contact_name',
        'quotation_date',
        'currency',
        'exchange_rate',
        'created_by',
        'assigned_to',
        'status',
        'approved_by',
        'approved_at',
        'subtotal',
        'vat_total',
        'total_amount',
        'notes',
    ];

    public function items()
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }
}
