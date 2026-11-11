<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalExportTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'phpunit-internal-key';

    public function test_exports_require_internal_key(): void
    {
        $this->getJson('/api/internal/exports/orders')->assertForbidden();
        $this->withHeaders(['X-Internal-Service-Key' => 'wrong'])->getJson('/api/internal/exports/revenue-items')->assertForbidden();
    }

    public function test_orders_export_uses_assignee_then_creator_as_salesperson(): void
    {
        DB::table('sales_orders')->insert([
            'order_number' => 'SO-1', 'order_date' => '2026-10-01', 'customer_id' => 5, 'status' => 'CONFIRMED',
            'subtotal' => 100, 'total_amount' => 108, 'created_by' => 'creator', 'assigned_to' => 'sales01',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sales_orders')->insert([
            'order_number' => 'SO-2', 'order_date' => '2026-10-02', 'customer_id' => 5, 'status' => 'CONFIRMED',
            'subtotal' => 50, 'total_amount' => 54, 'created_by' => 'creator', 'assigned_to' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $items = $this->withHeaders(['X-Internal-Service-Key' => self::KEY])
            ->getJson('/api/internal/exports/orders')->assertOk()->json('data.items');

        $this->assertSame(['SO-1', 'SO-2'], array_column($items, 'order_number'));
        $this->assertSame('sales01', $items[0]['salesperson']);
        $this->assertSame('creator', $items[1]['salesperson']);
    }

    public function test_revenue_items_page_with_after_id(): void
    {
        // Chỉ kiểm tra phân trang: bỏ qua FK tới bảng hóa đơn (không liên quan tới export)
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        foreach (['INV-1', 'INV-2', 'INV-3'] as $number) {
            DB::table('revenue_report_items')->insert([
                'invoice_id' => 1, 'invoice_item_id' => 1, 'invoice_number' => $number, 'order_number' => 'SO-X', 'product_id' => 9, 'quantity' => 2,
                'unit_price' => 10, 'subtotal' => 20, 'vat_amount' => 2, 'is_vat_invoiced' => true,
                'issue_date' => '2026-10-05', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $first = $this->withHeaders(['X-Internal-Service-Key' => self::KEY])
            ->getJson('/api/internal/exports/revenue-items?limit=2')->assertOk()->json('data');
        $this->assertSame(['INV-1', 'INV-2'], array_column($first['items'], 'invoice_number'));

        $second = $this->withHeaders(['X-Internal-Service-Key' => self::KEY])
            ->getJson('/api/internal/exports/revenue-items?limit=2&after_id='.$first['next_after_id'])->assertOk()->json('data');
        $this->assertSame(['INV-3'], array_column($second['items'], 'invoice_number'));
        $this->assertNull($second['next_after_id']);
    }
}
