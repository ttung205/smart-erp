<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VatInvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'vat_invoice_items';

    protected $fillable = [
        'vat_invoice_id',
        'sales_invoice_item_id',
        'revenue_report_item_id',
        'order_item_id',
        'product_id',
        'sku',
        'product_name',
        'quantity',
        'unit_price',
        'subtotal',
        'vat_percent',
        'vat_amount',
        'total_amount',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'subtotal' => 'float',
        'vat_percent' => 'float',
        'vat_amount' => 'float',
        'total_amount' => 'float',
    ];

    public function vatInvoice()
    {
        return $this->belongsTo(VatInvoice::class, 'vat_invoice_id');
    }

    public function salesInvoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class, 'sales_invoice_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
