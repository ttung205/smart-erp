<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarehousePostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_post_is_idempotent(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'invoice_date' => '2026-10-13',
            'created_by' => 'Test User',
            'warehouse_status' => 'PROCESSED',
            'accounting_status' => 'UNPOSTED',
        ]);
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => 100,
            'sku' => 'SKU-100',
            'product_name' => 'Test product',
            'requested_quantity' => 2,
            'quantity' => 2,
            'unit_price' => 100,
        ]);
        $headers = $this->jwtHeaders(['invoice.manage']);

        $this->withHeaders($headers)->postJson("/api/invoices/{$invoice->id}/warehouse-post")->assertOk();
        $this->withHeaders($headers)->postJson("/api/invoices/{$invoice->id}/warehouse-post")->assertOk();

        $this->assertSame(2.0, (float) $item->fresh()->delivered_quantity);
        Http::assertSentCount(1);
    }
}
