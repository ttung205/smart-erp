<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RevenueReportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_invoice_more_than_remaining_quantity(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-VAT-001',
            'invoice_date' => '2026-10-13',
            'created_by' => 'Test User',
        ]);
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => 100,
            'sku' => 'SKU-100',
            'product_name' => 'Test product',
            'quantity' => 2,
            'unit_price' => 100,
        ]);
        $reportItem = RevenueReportItem::create([
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $item->id,
            'account_code' => '1312',
            'quantity' => 2,
            'remaining_invoice_quantity' => 2,
            'unit_price' => 100,
            'vat_rate' => 10,
        ]);

        $response = $this->withHeaders($this->jwtHeaders(['invoice.manage']))->postJson('/api/vat-invoices', [
            'invoice_date' => '2026-10-13',
            'sales_invoice_id' => $invoice->id,
            'items' => [[
                'revenue_report_item_id' => $reportItem->id,
                'sales_invoice_item_id' => $item->id,
                'quantity' => 3,
            ]],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('vat_invoices', 0);
    }
}
