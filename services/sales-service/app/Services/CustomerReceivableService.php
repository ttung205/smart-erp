<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\VatInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class CustomerReceivableService
{
    public function createForVatInvoice(VatInvoice $vatInvoice, ?Invoice $salesInvoice = null, ?string $bearerToken = null): void
    {
        preg_match('/(\d+)/', (string) $salesInvoice?->payment_terms, $matches);
        $paymentTermDays = isset($matches[1]) ? max((int) $matches[1], 0) : 30;
        $invoiceDate = Carbon::parse($vatInvoice->invoice_date);

        Http::withToken((string) $bearerToken)
            ->acceptJson()
            ->timeout(config('services.finance.timeout'))
            ->post(config('services.finance.url').'/api/customer-receivables/sync', [
                'vat_invoice_id' => $vatInvoice->id,
                'sales_invoice_id' => $vatInvoice->sales_invoice_id,
                'customer_id' => $vatInvoice->customer_id,
                'customer_name' => $vatInvoice->customer_name ?: 'Khách hàng',
                'customer_tax_code' => $vatInvoice->customer_tax_code,
                'invoice_number' => $vatInvoice->invoice_number,
                'invoice_series' => $vatInvoice->invoice_series,
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $invoiceDate->copy()->addDays($paymentTermDays)->toDateString(),
                'currency' => $vatInvoice->currency ?: 'VND',
                'total_amount' => $vatInvoice->total_amount,
                'subtotal' => $vatInvoice->subtotal,
                'vat_amount' => $vatInvoice->vat_total,
            ])->throw();
    }
}
