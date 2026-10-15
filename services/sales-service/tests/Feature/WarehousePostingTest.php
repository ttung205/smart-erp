<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarehousePostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_post_is_idempotent(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        [$invoice, $item] = $this->makeInvoice();
        $headers = $this->jwtHeaders(['invoice.manage']);

        $this->withHeaders($headers)->postJson("/api/invoices/{$invoice->id}/warehouse-post")->assertOk();
        $this->withHeaders($headers)->postJson("/api/invoices/{$invoice->id}/warehouse-post")->assertOk();

        $this->assertSame(2.0, (float) $item->fresh()->delivered_quantity);
        Http::assertSentCount(1);
    }

    public function test_issue_uses_reserved_warehouse_and_forwards_token(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        [$invoice] = $this->makeInvoice(['inventory_warehouse_id' => 2]);
        $headers = $this->jwtHeaders(['invoice.manage']);

        // Không cho xuất ở kho khác kho đã giữ chỗ
        $this->withHeaders($headers)->postJson("/api/invoices/{$invoice->id}/warehouse-post", ['warehouse_id' => 1])
            ->assertStatus(422)->assertJsonPath('error.code', 'WAREHOUSE_MISMATCH');
        Http::assertNothingSent();

        $this->withHeaders($headers)->postJson("/api/invoices/{$invoice->id}/warehouse-post")->assertOk();
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/stocks/issue')
            && $request['warehouse_id'] === 2
            && $request->hasHeader('Authorization'));
    }

    public function test_cannot_issue_before_stock_is_reserved(): void
    {
        Http::fake();
        [$invoice] = $this->makeInvoice(['inventory_reservation_status' => 'FAILED']);

        $this->withHeaders($this->jwtHeaders(['invoice.manage']))->postJson("/api/invoices/{$invoice->id}/warehouse-post")
            ->assertStatus(409)->assertJsonPath('error.code', 'STOCK_NOT_RESERVED');
        Http::assertNothingSent();
    }

    /** @return array{0: Invoice, 1: InvoiceItem} */
    private function makeInvoice(array $attributes = []): array
    {
        $invoice = Invoice::create($attributes + [
            'invoice_number' => 'INV-TEST-001',
            'invoice_date' => '2026-10-13',
            'created_by' => 'Test User',
            'warehouse_status' => 'PROCESSED',
            'accounting_status' => 'UNPOSTED',
            'inventory_reservation_status' => 'RESERVED',
            'inventory_warehouse_id' => 1,
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

        return [$invoice, $item];
    }
}
