<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleReturnRequestItem;
use App\Models\VatInvoice;
use App\Models\VatInvoiceItem;
use App\Support\VatInvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VatInvoiceAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_adjustment_waits_for_approval_then_syncs_signed_difference(): void
    {
        Http::fake([
            '*/api/customer-receivables/sync' => Http::response(['success' => true], 201),
            '*/api/invoice-adjustments/sync' => Http::response(['success' => true]),
        ]);
        $invoice = VatInvoice::factory()->create([
            'invoice_number' => '00001243', 'subtotal' => 200, 'vat_total' => 20, 'total_amount' => 220,
        ]);
        $item = VatInvoiceItem::create([
            'vat_invoice_id' => $invoice->id, 'quantity' => 2, 'unit_price' => 100,
            'vat_percent' => 10, 'vat_amount' => 20, 'subtotal' => 200, 'total_amount' => 220,
        ]);
        $created = $this->postJson('/api/vat-invoice-adjustments', [
            'original_vat_invoice_id' => $invoice->id, 'direction' => 'DECREASE',
            'adjustment_type' => 'PRICE', 'adjustment_date' => '2026-10-15',
            'reason' => 'Giảm đơn giá theo thỏa thuận',
            'items' => [['original_vat_invoice_item_id' => $item->id, 'amount' => 10]],
        ], $this->jwtHeaders(['finance.create']))->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.total_delta', -22)->json('data');
        Http::assertNothingSent();
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/issue", [], $this->jwtHeaders(['finance.create']))->assertForbidden();
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/issue", [], $this->jwtHeaders(['finance.approve']))->assertUnprocessable();
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/approve", [], $this->jwtHeaders(['finance.create']))->assertForbidden();
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/approve", [], $this->jwtHeaders(['finance.approve']))
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
        Http::assertNothingSent();
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/issue", [], $this->jwtHeaders(['finance.approve']))
            ->assertOk()->assertJsonPath('data.status', 'ISSUED')
            ->assertJsonPath('data.issued_invoice.invoice_number', '00001244')
            ->assertJsonPath('data.issued_invoice.invoice_series', $invoice->invoice_series)
            ->assertJsonPath('data.issued_invoice.total_amount', -22);
        $this->assertDatabaseHas('vat_invoices', [
            'adjustment_id' => $created['id'], 'invoice_number' => '00001244',
            'invoice_series' => $invoice->invoice_series, 'status' => 'ISSUED',
        ]);
        $this->assertDatabaseHas('vat_invoice_items', [
            'adjustment_item_id' => $created['items'][0]['id'], 'subtotal' => -20, 'vat_amount' => -2,
        ]);
        $this->getJson('/api/vat-invoices', $this->jwtHeaders(['invoice.view']))
            ->assertOk()->assertJsonPath('data.0.invoice_number', '00001244')
            ->assertJsonPath('data.0.adjustment.original_invoice.id', $invoice->id);
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/issue", [], $this->jwtHeaders(['finance.approve']))
            ->assertOk()->assertJsonPath('data.issued_invoice.invoice_number', '00001244');
        $this->assertDatabaseCount('vat_invoices', 2);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/invoice-adjustments/sync')
            && $request['subtotal_delta'] === -20.0
            && $request['vat_delta'] === -2.0 && $request['total_delta'] === -22.0
            && $request['adjustment_number'] === '00001244');
        $this->getJson('/api/vat-invoices/next-number?series='.$invoice->invoice_series, $this->jwtHeaders(['invoice.create']))
            ->assertOk()->assertJsonPath('next_number', '00001245');
    }

    public function test_sale_return_creates_one_quantity_decrease_request_and_is_idempotent(): void
    {
        Http::fake([
            '*/api/customer-receivables/sync' => Http::response(['success' => true], 201),
            '*/api/invoice-adjustments/sync' => Http::response(['success' => true]),
        ]);
        $return = SaleReturn::factory()->create();
        $salesInvoice = Invoice::create(['invoice_number' => 'INV-RETURN-001', 'invoice_date' => '2026-10-15', 'created_by' => 'Test User']);
        $salesItem = InvoiceItem::create(['invoice_id' => $salesInvoice->id, 'product_id' => 100,
            'sku' => 'SKU-RETURN-100', 'product_name' => 'Hàng trả', 'quantity' => 2, 'unit_price' => 100]);
        $invoice = VatInvoice::factory()->create(['sales_invoice_id' => $salesInvoice->id]);
        VatInvoiceItem::create([
            'vat_invoice_id' => $invoice->id, 'sales_invoice_item_id' => $salesItem->id,
            'quantity' => 2, 'unit_price' => 100, 'vat_percent' => 10,
            'subtotal' => 200, 'vat_amount' => 20, 'total_amount' => 220,
        ]);
        $requestItem = SaleReturnRequestItem::create([
            'sale_return_request_id' => $return->sale_return_request_id,
            'invoice_id' => $salesInvoice->id, 'invoice_item_id' => $salesItem->id,
            'product_id' => 100, 'invoice_number' => $salesInvoice->invoice_number,
            'sku' => 'SKU-RETURN-100', 'product_name' => 'Hàng trả',
            'delivered_quantity' => 2, 'requested_quantity' => 1,
            'accepted_quantity' => 1, 'unit_price' => 100, 'line_total' => 100,
        ]);
        SaleReturnItem::create([
            'sale_return_id' => $return->id, 'sale_return_request_item_id' => $requestItem->id,
            'invoice_id' => $salesInvoice->id, 'invoice_item_id' => $salesItem->id,
            'product_id' => 100, 'invoice_number' => $salesInvoice->invoice_number,
            'sku' => 'SKU-RETURN-100', 'product_name' => 'Hàng trả',
            'quantity' => 1, 'unit_price' => 100, 'line_total' => 100,
        ]);
        $headers = $this->jwtHeaders(['finance.create']);
        $path = "/api/sale-returns/{$return->id}/vat-invoice-adjustments";
        $created = $this->postJson($path, [], $headers)->assertCreated()
            ->assertJsonPath('data.0.adjustment_type', 'QUANTITY')
            ->assertJsonPath('data.0.direction', 'DECREASE')
            ->assertJsonPath('data.0.total_delta', -110)->json('data.0');
        $this->postJson($path, [], $headers)->assertCreated()->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('vat_invoice_adjustments', 1);
        $this->getJson("/api/vat-invoice-adjustments/{$created['id']}", $this->jwtHeaders(['finance.view']))
            ->assertOk()->assertJsonCount(1, 'data.original_invoice.items');
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/approve", [], $this->jwtHeaders(['finance.approve']))
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/issue", [], $this->jwtHeaders(['finance.approve']))
            ->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->assertDatabaseHas('revenue_report_items', [
            'vat_invoice_adjustment_item_id' => $created['items'][0]['id'],
            'quantity' => -1, 'subtotal' => -100, 'vat_amount' => -10,
        ]);
    }

    public function test_retry_after_finance_failure_keeps_the_same_vat_invoice_number(): void
    {
        $attempts = 0;
        Http::fake(function ($request) use (&$attempts) {
            if (str_ends_with($request->url(), '/api/invoice-adjustments/sync')) {
                $attempts++;

                return Http::response(['success' => $attempts > 1], $attempts > 1 ? 200 : 500);
            }

            return Http::response(['success' => true], 201);
        });
        $invoice = VatInvoice::factory()->create([
            'invoice_number' => '00000001', 'subtotal' => 200, 'vat_total' => 20, 'total_amount' => 220,
        ]);
        $item = VatInvoiceItem::create([
            'vat_invoice_id' => $invoice->id, 'quantity' => 2, 'unit_price' => 100,
            'vat_percent' => 10, 'vat_amount' => 20, 'subtotal' => 200, 'total_amount' => 220,
        ]);
        $created = $this->postJson('/api/vat-invoice-adjustments', [
            'original_vat_invoice_id' => $invoice->id, 'direction' => 'DECREASE',
            'adjustment_type' => 'PRICE', 'adjustment_date' => '2026-10-15',
            'reason' => 'Price correction',
            'items' => [['original_vat_invoice_item_id' => $item->id, 'amount' => 10]],
        ], $this->jwtHeaders(['finance.create']))->assertCreated()->json('data');
        $headers = $this->jwtHeaders(['finance.approve']);
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/approve", [], $headers)->assertOk();
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/issue", [], $headers)->assertInternalServerError();
        $this->assertDatabaseHas('vat_invoice_adjustments', ['id' => $created['id'], 'status' => 'PENDING_FINANCE']);
        $this->assertDatabaseHas('vat_invoices', [
            'adjustment_id' => $created['id'], 'invoice_number' => '00000002', 'status' => 'PENDING_FINANCE',
        ]);
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/issue", [], $headers)
            ->assertOk()->assertJsonPath('data.issued_invoice.invoice_number', '00000002');
        $this->assertDatabaseCount('vat_invoices', 2);
        $this->assertSame(2, $attempts);
    }

    public function test_vat_adjustment_uses_percentage_points_and_current_taxable_amount(): void
    {
        Http::fake([
            '*/api/customer-receivables/sync' => Http::response(['success' => true], 201),
            '*/api/invoice-adjustments/sync' => Http::response(['success' => true]),
        ]);
        $invoice = VatInvoice::factory()->create([
            'subtotal' => 200, 'vat_total' => 20, 'total_amount' => 220,
        ]);
        $item = VatInvoiceItem::create([
            'vat_invoice_id' => $invoice->id, 'quantity' => 2, 'unit_price' => 100,
            'vat_percent' => 10, 'vat_amount' => 20, 'subtotal' => 200, 'total_amount' => 220,
        ]);
        $payload = [
            'original_vat_invoice_id' => $invoice->id, 'direction' => 'INCREASE',
            'adjustment_type' => 'VAT', 'adjustment_date' => '2026-10-15',
            'reason' => 'Change VAT rate',
            'items' => [['original_vat_invoice_item_id' => $item->id, 'amount' => 2]],
        ];
        $created = $this->postJson('/api/vat-invoice-adjustments', $payload, $this->jwtHeaders(['finance.create']))
            ->assertCreated()->assertJsonPath('data.subtotal_delta', 0)
            ->assertJsonPath('data.vat_delta', 4)
            ->assertJsonPath('data.items.0.vat_percent_delta', 2)->json('data');
        $approveHeaders = $this->jwtHeaders(['finance.approve']);
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/approve", [], $approveHeaders)->assertOk();
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/issue", [], $approveHeaders)
            ->assertOk()->assertJsonPath('data.issued_invoice.items.0.vat_percent', 12)
            ->assertJsonPath('data.issued_invoice.vat_total', 4);

        $payload['adjustment_type'] = 'PRICE';
        $payload['items'][0]['amount'] = 10;
        $priceAdjustment = $this->postJson('/api/vat-invoice-adjustments', $payload, $this->jwtHeaders(['finance.create']))
            ->assertCreated()->assertJsonPath('data.subtotal_delta', 20)
            ->assertJsonPath('data.vat_delta', 2.4)->json('data');
        $this->postJson("/api/vat-invoice-adjustments/{$priceAdjustment['id']}/approve", [], $approveHeaders)->assertOk();
        $this->postJson("/api/vat-invoice-adjustments/{$priceAdjustment['id']}/issue", [], $approveHeaders)->assertOk();

        $payload['adjustment_type'] = 'VAT';
        $payload['direction'] = 'DECREASE';
        $payload['items'][0]['amount'] = 1;
        $this->postJson('/api/vat-invoice-adjustments', $payload, $this->jwtHeaders(['finance.create']))
            ->assertCreated()->assertJsonPath('data.vat_delta', -2.2);
        $payload['items'][0]['amount'] = 13;
        $this->postJson('/api/vat-invoice-adjustments', $payload, $this->jwtHeaders(['finance.create']))
            ->assertUnprocessable();
    }

    public function test_issue_advances_past_existing_vat_invoice_when_sequence_is_stale(): void
    {
        Http::fake([
            '*/api/customer-receivables/sync' => Http::response(['success' => true], 201),
            '*/api/invoice-adjustments/sync' => Http::response(['success' => true]),
        ]);
        $invoice = VatInvoice::factory()->create([
            'invoice_number' => '00000001', 'subtotal' => 200, 'vat_total' => 20, 'total_amount' => 220,
        ]);
        $item = VatInvoiceItem::create([
            'vat_invoice_id' => $invoice->id, 'quantity' => 2, 'unit_price' => 100,
            'vat_percent' => 10, 'vat_amount' => 20, 'subtotal' => 200, 'total_amount' => 220,
        ]);
        $this->assertSame('00000002', VatInvoiceNumberGenerator::preview($invoice->invoice_series));
        $created = $this->postJson('/api/vat-invoice-adjustments', [
            'original_vat_invoice_id' => $invoice->id, 'direction' => 'DECREASE',
            'adjustment_type' => 'PRICE', 'adjustment_date' => '2026-10-15',
            'reason' => 'Price correction',
            'items' => [['original_vat_invoice_item_id' => $item->id, 'amount' => 10]],
        ], $this->jwtHeaders(['finance.create']))->assertCreated()->json('data');
        $headers = $this->jwtHeaders(['finance.approve']);
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/approve", [], $headers)->assertOk();
        $this->postJson("/api/vat-invoice-adjustments/{$created['id']}/issue", [], $headers)
            ->assertOk()->assertJsonPath('data.issued_invoice.invoice_number', '00000002');
        $this->assertSame('00000003', VatInvoiceNumberGenerator::preview($invoice->invoice_series));
        $this->assertDatabaseCount('vat_invoices', 2);
    }
}
