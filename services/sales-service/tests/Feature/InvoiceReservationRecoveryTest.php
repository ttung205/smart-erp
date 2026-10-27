<?php

namespace Tests\Feature;

use App\Models\DeliveryRequest;
use App\Models\DeliveryRequestItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Kịch bản kiến trúc "Inventory đã giữ hàng nhưng Sales bị timeout": chứng từ không được tạo, hàng không bị treo,
 * số chứng từ không bị cấp lại.
 */
class InvoiceReservationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function today(): string
    {
        return now()->format('dmy');
    }

    public function test_invoice_number_follows_customer_rule_and_ignores_client_number(): void
    {
        Http::fake(['*/stocks/reserve/batch' => Http::response(['success' => true], 200)]);

        $number = $this->createInvoice($this->approvedDelivery(), ['invoice_number' => 'INV-2026-1234'])
            ->assertCreated()->json('data.invoice_number');

        $this->assertSame("INV-RE-02592-{$this->today()}", $number);
    }

    public function test_reservation_timeout_rolls_back_releases_stock_and_never_reuses_the_number(): void
    {
        $inventoryTimesOut = true;
        Http::fake(function (Request $request) use (&$inventoryTimesOut) {
            if ($inventoryTimesOut && str_ends_with($request->url(), '/stocks/reserve/batch')) {
                throw new ConnectionException('Operation timed out after 3000 milliseconds');
            }

            return Http::response(['success' => true, 'data' => []], 200);
        });
        $delivery = $this->approvedDelivery();

        $this->createInvoice($delivery)->assertStatus(503)->assertJsonPath('error.code', 'DEPENDENCY_UNAVAILABLE');

        $timedOutNumber = "INV-RE-02592-{$this->today()}";
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame('APPROVED', $delivery->fresh()->status);
        // Inventory có thể đã giữ xong trước khi timeout: Sales nhả hàng của đúng số chứng từ này
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/stocks/release/document')
            && $request['order_id'] === $timedOutNumber);

        // Lần lập sau không dùng lại số vừa thất bại (Inventory sẽ nhầm là chứng từ đã giữ hàng)
        $inventoryTimesOut = false;
        $this->createInvoice($delivery)->assertCreated()->assertJsonPath('data.invoice_number', "{$timedOutNumber}-A");
    }

    public function test_inventory_server_error_is_treated_as_uncertain_and_compensated(): void
    {
        Http::fake([
            '*/stocks/reserve/batch' => Http::response(['success' => false, 'error' => ['code' => 'INTERNAL_SERVER_ERROR']], 500),
            '*/stocks/release/document' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $this->createInvoice($this->approvedDelivery())->assertStatus(502);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/stocks/release/document'));
    }

    public function test_insufficient_stock_is_definitive_and_needs_no_compensation(): void
    {
        Http::fake(['*/stocks/reserve/batch' => Http::response(['success' => false, 'error' => [
            'code' => 'INSUFFICIENT_STOCK', 'details' => ['product_id' => 100, 'requested' => 20, 'available' => 3],
        ]], 409)]);

        $this->createInvoice($this->approvedDelivery())->assertStatus(409);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'release'));
        $this->assertDatabaseCount('invoices', 0);
    }

    private function approvedDelivery(): DeliveryRequest
    {
        $order = SalesOrder::create([
            'order_number' => 'OD-RE-02592-081026', 'order_date' => '2026-10-08', 'status' => 'APPROVED', 'customer_code' => 'RE-02592',
        ]);
        $orderItem = SalesOrderItem::create(['order_id' => $order->id, 'product_id' => 100, 'sku' => 'SM456-SHA', 'product_name' => 'SM456', 'quantity' => 40]);
        $delivery = DeliveryRequest::create([
            'order_id' => $order->id, 'request_number' => 'DR-TEST-001', 'warehouse_id' => 2, 'warehouse_name' => 'Kho 2',
            'shipping_address' => 'Bỉm Sơn', 'request_date' => '2026-10-15', 'status' => 'APPROVED',
        ]);
        DeliveryRequestItem::create([
            'delivery_request_id' => $delivery->id, 'order_item_id' => $orderItem->id, 'product_id' => 100, 'sku' => 'SM456-SHA', 'quantity' => 20,
        ]);

        return $delivery;
    }

    /** @param  array<string, mixed>  $extra */
    private function createInvoice(DeliveryRequest $delivery, array $extra = []): TestResponse
    {
        $line = $delivery->items()->first();

        return $this->withHeaders($this->jwtHeaders(['sale-invoice.create']))->postJson('/api/invoices', [
            'delivery_request_id' => $delivery->id,
            'invoice_date' => '2026-10-15',
            'items' => [[
                'delivery_request_item_id' => $line->id, 'order_item_id' => $line->order_item_id, 'product_id' => 100,
                'sku' => 'SM456-SHA', 'quantity' => 20, 'unit_price' => 1000,
            ]],
            ...$extra,
        ]);
    }
}
