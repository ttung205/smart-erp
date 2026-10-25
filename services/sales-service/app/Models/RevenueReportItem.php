<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevenueReportItem extends Model
{
    use HasFactory;

    protected $table = 'revenue_report_items';

    protected $fillable = [
        'invoice_id',
        'invoice_number',
        'invoice_item_id',
        'vat_invoice_id',
        'vat_invoice_number',
        'vat_invoice_series',
        'vat_invoice_item_id',
        'vat_invoice_adjustment_item_id',
        'order_id',
        'order_number',
        'product_id',
        'sku',
        'product_name',
        'account_code',
        'is_vat_invoiced',
        'quantity',
        'invoiced_quantity',
        'remaining_invoice_quantity',
        'currency',
        'exchange_rate',
        'unit_price',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'total_amount',
        'customer_id',
        'salesperson_id',
        'issue_date',
        'vat_invoice_date',
    ];

    protected $casts = [
        'is_vat_invoiced' => 'boolean',
        'quantity' => 'float',
        'invoiced_quantity' => 'float',
        'remaining_invoice_quantity' => 'float',
        'exchange_rate' => 'float',
        'unit_price' => 'float',
        'subtotal' => 'float',
        'vat_rate' => 'float',
        'vat_amount' => 'float',
        'total_amount' => 'float',
        'customer_id' => 'integer',
        'salesperson_id' => 'integer',
    ];

    protected $appends = [
        'customer_name',
        'salesperson_name',
        'account_name',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }

    public function vatInvoice()
    {
        return $this->belongsTo(VatInvoice::class, 'vat_invoice_id');
    }

    public function vatInvoiceItem()
    {
        return $this->belongsTo(VatInvoiceItem::class, 'vat_invoice_item_id');
    }

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function getCustomerNameAttribute()
    {
        return $this->order?->customer_name ?: ($this->invoice?->customer_name ?? '');
    }

    // salesperson_id = người phụ trách đơn (hoặc người tạo nếu chưa phân công)
    public function getSalespersonNameAttribute()
    {
        $order = $this->order;
        if (! $order) {
            return '';
        }

        return (int) $this->salesperson_id === (int) $order->assigned_to ? $order->assigned_to_name : $order->created_by_name;
    }

    public function getAccountNameAttribute()
    {
        return $this->account_code === '1311'
            ? 'TK 1311: Đã xuất kho & Đã xuất HĐ VAT'
            : 'TK 1312: Đã xuất kho & Chưa xuất HĐ VAT';
    }
}
