<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RevenueReportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerReceivableTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuing_vat_invoice_sends_receivable_to_finance_service(): void
    {
        Http::fake(['*/api/customer-receivables/sync' => Http::response(['success' => true], 201)]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-AR-001', 'invoice_date' => '2026-10-13',
            'payment_terms' => 'Công nợ 15 ngày', 'created_by' => 'Test User',
        ]);
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id, 'product_id' => 100, 'sku' => 'SKU-AR-100',
            'product_name' => 'Sản phẩm công nợ', 'quantity' => 2, 'unit_price' => 100,
        ]);
        $reportItem = RevenueReportItem::create([
            'invoice_id' => $invoice->id, 'invoice_item_id' => $item->id, 'account_code' => '1312',
            'quantity' => 2, 'remaining_invoice_quantity' => 2, 'unit_price' => 100, 'vat_rate' => 10,
        ]);

        $this->withHeaders($this->jwtHeaders(['invoice.manage']))->postJson('/api/vat-invoices', [
            'invoice_date' => '2026-10-13', 'sales_invoice_id' => $invoice->id,
            'items' => [['revenue_report_item_id' => $reportItem->id, 'sales_invoice_item_id' => $item->id, 'quantity' => 2]],
        ])->assertCreated();

        Http::assertSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:8008/api/customer-receivables/sync'
            && $request['invoice_date'] === '2026-10-13'
            && $request['due_date'] === '2026-10-28'
            && $request['total_amount'] === 220.0);
    }
}
