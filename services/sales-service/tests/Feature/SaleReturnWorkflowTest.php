<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SaleReturnRequest;
use App\Models\SaleReturnRequestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaleReturnWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_user_can_start_inspection_and_reject_request(): void
    {
        $returnRequest = SaleReturnRequest::factory()->create();
        $headers = $this->jwtHeaders(['sale_return.technical']);

        $this->postJson("/api/sale-return-requests/{$returnRequest->id}/start-inspection", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', SaleReturnRequest::INSPECTING);

        $this->postJson("/api/sale-return-requests/{$returnRequest->id}/reject", ['reason' => 'Không đúng điều kiện trả'], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', SaleReturnRequest::REJECTED);

        $this->assertDatabaseHas('sale_return_requests', [
            'id' => $returnRequest->id,
            'status' => SaleReturnRequest::REJECTED,
            'rejection_reason' => 'Không đúng điều kiện trả',
        ]);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $returnRequest = SaleReturnRequest::factory()->create(['status' => SaleReturnRequest::COMPLETED]);

        $this->postJson(
            "/api/sale-return-requests/{$returnRequest->id}/start-inspection",
            [],
            $this->jwtHeaders(['sale_return.technical'])
        )->assertStatus(409);
    }

    public function test_quality_result_automatically_uses_the_full_requested_quantity(): void
    {
        $returnRequest = SaleReturnRequest::factory()->create(['status' => SaleReturnRequest::INSPECTING]);
        $item = $this->createAcceptedItem($returnRequest);
        $item->update(['quality_status' => 'PENDING', 'accepted_quantity' => 0]);

        $this->putJson(
            "/api/sale-return-requests/{$returnRequest->id}/inspection",
            ['items' => [['id' => $item->id, 'quality_status' => 'ELIGIBLE']]],
            $this->jwtHeaders(['sale_return.technical'])
        )->assertOk();

        $this->assertDatabaseHas('sale_return_request_items', [
            'id' => $item->id,
            'quality_status' => 'ELIGIBLE',
            'accepted_quantity' => 1,
            'rejected_quantity' => 0,
        ]);
    }

    public function test_posting_creates_a_read_only_sale_return(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []])]);
        $returnRequest = SaleReturnRequest::factory()->create([
            'status' => SaleReturnRequest::PENDING_POSTING,
            'technical_warehouse_id' => 1,
            'technical_warehouse_name' => 'Kho kỹ thuật',
            'sales_warehouse_id' => 2,
            'sales_warehouse_name' => 'Kho bán',
        ]);
        $this->createAcceptedItem($returnRequest);

        $response = $this->postJson(
            "/api/sale-return-requests/{$returnRequest->id}/post",
            [],
            $this->jwtHeaders(['sale_return.post'])
        )->assertOk()->assertJsonPath('data.request_number', $returnRequest->request_number);

        $returnId = $response->json('data.id');
        $this->getJson("/api/sale-returns/{$returnId}", $this->jwtHeaders(['sale_return.view']))
            ->assertOk()
            ->assertJsonPath('data.id', $returnId);

        $this->assertDatabaseCount('sale_returns', 1);
        $this->assertDatabaseHas('sale_return_requests', ['id' => $returnRequest->id, 'status' => SaleReturnRequest::COMPLETED]);
    }

    public function test_receiving_to_technical_warehouse_updates_both_inventory_balances(): void
    {
        Http::fake(fn ($request) => str_ends_with($request->url(), '/warehouses')
            ? Http::response(['success' => true, 'data' => [['id' => 2, 'name' => 'Kho kỹ thuật', 'is_active' => true, 'can_sell' => false]]])
            : Http::response(['success' => true, 'data' => []]));
        $returnRequest = SaleReturnRequest::factory()->create(['status' => SaleReturnRequest::INSPECTING]);
        $this->createAcceptedItem($returnRequest);

        $this->postJson(
            "/api/sale-return-requests/{$returnRequest->id}/receive-technical",
            ['warehouse_id' => 2, 'warehouse_name' => 'Kho kỹ thuật'],
            $this->jwtHeaders(['sale_return.technical'])
        )->assertOk()->assertJsonPath('data.status', SaleReturnRequest::TECHNICAL_RECEIVED);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->hasHeader('Authorization')
            && $request['reference_doc'] === "{$returnRequest->request_number}-TECH"
            && $request['items'][0]['quantity'] === 1);
    }

    private function createAcceptedItem(SaleReturnRequest $returnRequest): SaleReturnRequestItem
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST',
            'customer_name' => 'Khách hàng kiểm thử',
            'invoice_date' => now()->toDateString(),
            'warehouse_status' => 'DELIVERED',
            'accounting_status' => 'POSTED',
        ]);
        $invoiceItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'sku' => 'SKU-TEST',
            'product_name' => 'Sản phẩm kiểm thử',
            'product_id' => 69,
            'quantity' => 2,
            'unit_price' => 100000,
        ]);

        return SaleReturnRequestItem::create([
            'sale_return_request_id' => $returnRequest->id,
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $invoiceItem->id,
            'product_id' => 69,
            'invoice_number' => 'INV-TEST',
            'sku' => 'SKU-TEST',
            'product_name' => 'Sản phẩm kiểm thử',
            'unit' => 'Cái',
            'delivered_quantity' => 2,
            'requested_quantity' => 1,
            'accepted_quantity' => 1,
            'quality_status' => 'ELIGIBLE',
            'unit_price' => 100000,
            'line_total' => 100000,
        ]);
    }
}
