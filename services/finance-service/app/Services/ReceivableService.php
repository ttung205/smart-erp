<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CustomerReceivable;

class ReceivableService
{
    /** @param array<string, mixed> $data */
    public function syncVatInvoice(array $data): CustomerReceivable
    {
        $accountId = ChartOfAccount::where('code', '1311')->value('id');

        $receivable = CustomerReceivable::firstOrNew(['source_key' => 'VAT:'.$data['vat_invoice_id']]);
        $receivable->fill([
            'source_vat_invoice_id' => $data['vat_invoice_id'],
            'source_sales_invoice_id' => $data['sales_invoice_id'] ?? null,
            'source_type' => 'VAT_INVOICE',
            'customer_id' => $data['customer_id'] ?? null,
            'customer_name' => $data['customer_name'],
            'customer_tax_code' => $data['customer_tax_code'] ?? null,
            'invoice_number' => $data['invoice_number'],
            'invoice_series' => $data['invoice_series'] ?? null,
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'] ?? $data['invoice_date'],
            'currency' => $data['currency'] ?? 'VND',
            'account_id' => $accountId,
            'original_amount' => $data['total_amount'],
        ]);
        $receivable->paid_amount ??= 0;
        $receivable->outstanding_amount = max((float) $data['total_amount'] - (float) $receivable->paid_amount, 0);
        $receivable->status = $receivable->outstanding_amount <= 0 ? 'PAID' : ($receivable->paid_amount > 0 ? 'PARTIALLY_PAID' : 'OPEN');
        $receivable->save();

        return $receivable;
    }
}
