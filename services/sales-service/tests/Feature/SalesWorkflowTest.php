<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_must_follow_approval_workflow(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'OD-TEST-001',
            'order_date' => '2026-10-13',
            'status' => 'DRAFT',
        ]);

        $headers = $this->jwtHeaders(['order.update', 'order.approve']);

        $this->withHeaders($headers)
            ->postJson("/api/orders/{$order->id}/approve")
            ->assertStatus(409);

        $this->withHeaders($headers)
            ->postJson("/api/orders/{$order->id}/submit-approval")
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson("/api/orders/{$order->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'APPROVED', 'approved_by' => 42]);
    }

    public function test_quotation_conversion_uses_server_values_and_is_idempotent(): void
    {
        $quotation = Quotation::create([
            'quotation_number' => 'QT-TEST-001',
            'customer_name' => 'Test Customer',
            'quotation_date' => '2026-10-13',
            'status' => 'APPROVED',
            'currency' => 'VND',
            'exchange_rate' => 1,
        ]);
        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'sku' => 'SKU-100',
            'quantity' => 2,
            'selling_price' => 100,
            'vat_percent' => 10,
            'subtotal' => 200,
            'status' => 'WIN',
        ]);
        $headers = $this->jwtHeaders(['order.create']);
        $payload = [
            'subtotal' => 1,
            'vat_total' => 0,
            'total_amount' => 1,
            'items' => [[
                'id' => $item->id,
                'quantity' => 999,
                'selling_price' => 1,
            ]],
        ];

        $this->withHeaders($headers)->postJson("/api/quotations/{$quotation->id}/create-so", $payload)->assertCreated();
        $this->withHeaders($headers)->postJson("/api/quotations/{$quotation->id}/create-so", $payload)->assertOk();

        $this->assertDatabaseCount('sales_orders', 1);
        $this->assertDatabaseHas('sales_orders', [
            'quotation_id' => $quotation->id,
            'subtotal' => 200,
            'vat_total' => 20,
            'total_amount' => 220,
        ]);
        $this->assertDatabaseHas('sales_order_items', [
            'quotation_item_id' => $item->id,
            'quantity' => 2,
            'selling_price' => 100,
            'subtotal' => 200,
        ]);
    }
}
