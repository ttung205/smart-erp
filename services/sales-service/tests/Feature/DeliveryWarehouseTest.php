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
        Http::fake(['*/stocks/reserve' => Http::response(['success' => true], 200)]);
        $order = SalesOrder::create(['order_number' => 'OD-RE-02592-081026-A', 'order_date' => '2026-10-08', 'status' => 'APPROVED']);
        $orderItem = SalesOrderItem::create(['order_id' => $order->id, 'product_id' => 100, 'sku' => 'SM456-SHA', 'product_name' => 'SM456', 'quantity' => 40]);
        $delivery = DeliveryRequest::create([
            'order_id' => $order->id, 'request_number' => 'DR-TEST-001', 'warehouse_id' => 2, 'warehouse_name' => 'Kho Kĩ Thuật',
            'shipping_address' => 'Bỉm Sơn', 'request_date' => '2026-10-15', 'status' => 'APPROVED',
        ]);
        $deliveryItem = DeliveryRequestItem::create([
            'delivery_request_id' => $delivery->id, 'order_item_id' => $orderItem->id, 'product_id' => 100, 'sku' => 'SM456-SHA', 'quantity' => 20,
        ]);

        $invoiceId = $this->withHeaders($this->jwtHeaders(['sale-invoice.create']))->postJson('/api/invoices', [
            'delivery_request_id' => $delivery->id,
            'invoice_date' => '2026-10-15',
            'items' => [['delivery_request_item_id' => $deliveryItem->id, 'order_item_id' => $orderItem->id, 'product_id' => 100, 'sku' => 'SM456-SHA', 'quantity' => 20, 'unit_price' => 1000]],
        ])->assertCreated()->json('data.id');

        // Tên kho không chứa từ khóa nào → trước đây bị đoán ra kho 1; giờ phải giữ chỗ ở kho 2 đã chọn
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/stocks/reserve')
            && $request['warehouse_id'] === 2 && $request->hasHeader('Authorization'));
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'inventory_reservation_status' => 'RESERVED', 'inventory_warehouse_id' => 2]);
    }
}
