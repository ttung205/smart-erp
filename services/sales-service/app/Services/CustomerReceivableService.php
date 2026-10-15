<?php

namespace App\Services;

use App\Models\CustomerReceivable;
use App\Models\Invoice;
use App\Models\VatInvoice;
use Illuminate\Support\Carbon;

class CustomerReceivableService
{
    public function createForVatInvoice(VatInvoice $vatInvoice, ?Invoice $salesInvoice = null): CustomerReceivable
    {
        preg_match('/(\d+)/', (string) $salesInvoice?->payment_terms, $matches);
        $paymentTermDays = isset($matches[1]) ? max((int) $matches[1], 0) : 30;
        $invoiceDate = Carbon::parse($vatInvoice->invoice_date);

        return CustomerReceivable::firstOrCreate(
            ['vat_invoice_id' => $vatInvoice->id],
            [
                'customer_id' => $vatInvoice->customer_id,
                'customer_name' => $vatInvoice->customer_name ?: 'Khách hàng',
                'customer_tax_code' => $vatInvoice->customer_tax_code,
                'invoice_number' => $vatInvoice->invoice_number,
                'invoice_series' => $vatInvoice->invoice_series,
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $invoiceDate->copy()->addDays($paymentTermDays)->toDateString(),
                'currency' => $vatInvoice->currency ?: 'VND',
                'original_amount' => $vatInvoice->total_amount,
                'paid_amount' => 0,
                'outstanding_amount' => $vatInvoice->total_amount,
                'status' => 'OPEN',
            ],
        );
    }
}
