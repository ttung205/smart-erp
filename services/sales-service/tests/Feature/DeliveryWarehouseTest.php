<?php

namespace Tests\Feature;

use App\Models\DeliveryRequest;
use App\Models\DeliveryRequestItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryWarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_request_stores_selected_sellable_warehouse(): void
    {
        Http::fake(['*/api/v1/inventory/warehouses' => Http::response(['data' => [
            ['id' => 1, 'code' => 'WH-MAIN', 'name' => 'Kho Chính', 'is_active' => true, 'can_sell' => true],
            ['id' => 2, 'code' => 'WH-TECH', 'name' => 'Kho Kỹ Thuật', 'is_active' => true, 'can_sell' => false],
        ]])]);
        $order = SalesOrder::create(['order_number' => 'OD-RE-02592-081026-A', 'order_date' => '2026-10-08', 'status' => 'APPROVED']);
        $item = SalesOrderItem::create(['order_id' => $order->id, 'sku' => 'SM456-SHA', 'product_name' => 'SM456', 'quantity' => 40]);
        $headers = $this->jwtHeaders(['delivery.create']);
        $payload = fn (int $warehouseId) => [
            'order_id' => $order->id, 'warehouse_id' => $warehouseId, 'warehouse_name' => 'Tên do frontend gửi',
            'shipping_address' => 'Thị xã Bỉm Sơn, Thanh Hóa',
            'items' => [['order_item_id' => $item->id, 'sku' => 'SM456-SHA', 'quantity' => 20]],
        ];

        // Kho kỹ thuật không được phép bán
        $this->withHeaders($headers)->postJson('/api/deliveries', $payload(2))
            ->assertUnprocessable()->assertJsonValidationErrors('warehouse_id');

        $this->withHeaders($headers)->postJson('/api/deliveries', $payload(1))->assertCreated()
            ->assertJsonPath('data.warehouse_id', 1)
            ->assertJsonPath('data.warehouse_name', 'Kho Chính'); // lấy tên chuẩn từ Inventory

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization'));
    }

    public function test_invoice_reserves_stock_in_delivery_request_warehouse(): void
    {
        Http::fake(['*/stocks/reserve/batch' => Http::response(['success' => true], 200)]);
        [$delivery, $lines] = $this->approvedDelivery([20]);

        $invoiceId = $this->createInvoice($delivery, $lines)->assertCreated()->json('data.id');

        // Tên kho không chứa từ khóa nào → trước đây bị đoán ra kho 1; giờ phải giữ chỗ ở kho 2 đã chọn.
        // Cả chứng từ giữ bằng MỘT lần gọi batch, order_id = số chứng từ (idempotency key phía Inventory)
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/stocks/reserve/batch')
            && $request['items'] === [['warehouse_id' => 2, 'product_id' => 100, 'quantity' => 20]]
            && str_starts_with($request['order_id'], 'INV-') && $request->hasHeader('Authorization'));
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'inventory_reservation_status' => 'RESERVED', 'inventory_warehouse_id' => 2]);
    }

    public function test_invoice_sends_every_line_in_one_batch_even_for_same_product(): void
    {
        Http::fake(['*/stocks/reserve/batch' => Http::response(['success' => true], 200)]);
        [$delivery, $lines] = $this->approvedDelivery([5, 7]);

        $this->createInvoice($delivery, $lines)->assertCreated();

        // Trước đây giữ từng dòng: dòng thứ 2 cùng sản phẩm bị Inventory coi là trùng chứng từ và bỏ qua
        Http::assertSent(fn ($request) => count($request['items']) === 2
            && array_sum(array_column($request['items'], 'quantity')) === 12);
    }

    public function test_invoice_is_not_created_when_stock_is_insufficient(): void
    {
        // Kho còn 3, yêu cầu giao thứ 2 cần 2 trong khi chứng từ đầu đã giữ 2 → Inventory trả thiếu hàng
        Http::fake(['*/stocks/reserve/batch' => Http::response(['success' => false, 'error' => [
            'code' => 'INSUFFICIENT_STOCK', 'message' => 'Số lượng tồn kho khả dụng không đủ',
            'details' => ['product_id' => 100, 'warehouse_id' => 2, 'requested' => 2, 'available' => 1],
        ]], 409)]);
        [$delivery, $lines] = $this->approvedDelivery([2]);

        $this->createInvoice($delivery, $lines)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK')
            ->assertJsonPath('error.message', 'Không đủ hàng để lập chứng từ: SKU SM456-SHA cần 2, kho chỉ còn 1 khả dụng.');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertDatabaseHas('delivery_requests', ['id' => $delivery->id, 'status' => 'APPROVED']); // vẫn lập chứng từ lại được
    }

    public function test_invoice_is_not_created_when_inventory_is_down(): void
    {
        Http::fake(['*/stocks/reserve/batch' => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);
        [$delivery, $lines] = $this->approvedDelivery([2]);

        $this->createInvoice($delivery, $lines)->assertStatus(503)->assertJsonPath('error.code', 'DEPENDENCY_UNAVAILABLE');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_cancel_releases_stock_and_returns_quantity_to_sales_order(): void
    {
        Http::fake([
            '*/stocks/reserve/batch' => Http::response(['success' => true], 200),
            '*/stocks/release/document' => Http::response(['success' => true, 'data' => []], 200),
        ]);
        [$delivery, $lines] = $this->approvedDelivery([20]);
        $invoice = $this->createInvoice($delivery, $lines)->assertCreated()->json('data');
        $manage = $this->jwtHeaders(['invoice.manage']);

        $this->withHeaders($manage)->postJson("/api/invoices/{$invoice['id']}/cancel", [])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->withHeaders($manage)->postJson("/api/invoices/{$invoice['id']}/cancel", ['reason' => 'Khách đổi ý'])->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/stocks/release/document')
            && $request['order_id'] === $invoice['invoice_number']);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice['id'], 'warehouse_status' => 'CANCELLED', 'inventory_reservation_status' => 'RELEASED',
            'cancelled_by_id' => 42, 'cancel_reason' => 'Khách đổi ý',
        ]);
        // Yêu cầu giao về 0 và đóng lại → đơn bán còn đủ 40 chưa giao, lập yêu cầu giao mới được
        $this->assertDatabaseHas('delivery_request_items', ['id' => $lines[0][1]->id, 'quantity' => 0]);
        $this->assertDatabaseHas('delivery_requests', ['id' => $delivery->id, 'status' => 'CANCELLED']);
        $requested = \Illuminate\Support\Facades\DB::table('delivery_request_items')->where('order_item_id', $lines[0][0]->id)->sum('quantity');
        $this->assertEquals(0, $requested);

        // Hủy lần nữa: idempotent; chứng từ đã hủy không thao tác tiếp được
        $this->withHeaders($manage)->postJson("/api/invoices/{$invoice['id']}/cancel", ['reason' => 'x'])->assertOk();
        $this->withHeaders($manage)->postJson("/api/invoices/{$invoice['id']}/send-to-warehouse")
            ->assertStatus(409)->assertJsonPath('error.code', 'INVOICE_CANCELLED');
    }

    public function test_cancel_keeps_invoice_when_inventory_cannot_release(): void
    {
        Http::fake([
            '*/stocks/reserve/batch' => Http::response(['success' => true], 200),
            '*/stocks/release/document' => Http::response(null, 500),
        ]);
        [$delivery, $lines] = $this->approvedDelivery([20]);
        $invoiceId = $this->createInvoice($delivery, $lines)->assertCreated()->json('data.id');

        $this->withHeaders($this->jwtHeaders(['invoice.manage']))->postJson("/api/invoices/{$invoiceId}/cancel", ['reason' => 'Sai'])
            ->assertStatus(502);

        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'warehouse_status' => 'PENDING_WAREHOUSE', 'inventory_reservation_status' => 'RESERVED']);
        $this->assertDatabaseHas('delivery_request_items', ['id' => $lines[0][1]->id, 'quantity' => 20]);
    }

    public function test_issued_invoice_cannot_be_cancelled(): void
    {
        Http::fake(['*/stocks/reserve/batch' => Http::response(['success' => true], 200)]);
        [$delivery, $lines] = $this->approvedDelivery([20]);
        $invoiceId = $this->createInvoice($delivery, $lines)->assertCreated()->json('data.id');
        \App\Models\Invoice::whereKey($invoiceId)->update(['inventory_issue_status' => 'ISSUED', 'warehouse_status' => 'DELIVERED']);

        $this->withHeaders($this->jwtHeaders(['invoice.manage']))->postJson("/api/invoices/{$invoiceId}/cancel", ['reason' => 'Muộn'])
            ->assertStatus(409)->assertJsonPath('error.code', 'INVOICE_ALREADY_ISSUED');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'release'));
    }

    /** @return array{0: DeliveryRequest, 1: list<array{0: SalesOrderItem, 1: DeliveryRequestItem}>} */
    private function approvedDelivery(array $quantities): array
    {
        $order = SalesOrder::create(['order_number' => 'OD-RE-02592-081026-A', 'order_date' => '2026-10-08', 'status' => 'APPROVED']);
        $delivery = DeliveryRequest::create([
            'order_id' => $order->id, 'request_number' => 'DR-TEST-001', 'warehouse_id' => 2, 'warehouse_name' => 'Kho Kĩ Thuật',
            'shipping_address' => 'Bỉm Sơn', 'request_date' => '2026-10-15', 'status' => 'APPROVED',
        ]);
        $lines = [];
        foreach ($quantities as $quantity) {
            $orderItem = SalesOrderItem::create(['order_id' => $order->id, 'product_id' => 100, 'sku' => 'SM456-SHA', 'product_name' => 'SM456', 'quantity' => 40]);
            $lines[] = [$orderItem, DeliveryRequestItem::create([
                'delivery_request_id' => $delivery->id, 'order_item_id' => $orderItem->id, 'product_id' => 100, 'sku' => 'SM456-SHA', 'quantity' => $quantity,
            ])];
        }

        return [$delivery, $lines];
    }

    private function createInvoice(DeliveryRequest $delivery, array $lines): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->jwtHeaders(['sale-invoice.create']))->postJson('/api/invoices', [
            'delivery_request_id' => $delivery->id,
            'invoice_date' => '2026-10-15',
            'items' => array_map(fn (array $line) => [
                'delivery_request_item_id' => $line[1]->id, 'order_item_id' => $line[0]->id, 'product_id' => 100,
                'sku' => 'SM456-SHA', 'quantity' => $line[1]->quantity, 'unit_price' => 1000,
            ], $lines),
        ]);
    }
}
