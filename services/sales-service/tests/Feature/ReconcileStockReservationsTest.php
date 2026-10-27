<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReconcileStockReservationsTest extends TestCase
{
    use RefreshDatabase;

    private function fakeInventory(int $releaseStatus = 200): void
    {
        config(['services.internal.key' => 'internal-test-key']);
        Http::fake([
            '*/stocks/reservations/documents*' => Http::response(['success' => true, 'data' => [
                ['document_id' => 'INV-ORPHAN-271026', 'reserved_at' => '2026-10-27T01:00:00Z', 'lines' => []],
                ['document_id' => 'INV-ACTIVE-271026', 'reserved_at' => '2026-10-27T01:00:00Z', 'lines' => []],
                ['document_id' => 'INV-CANCELLED-271026', 'reserved_at' => '2026-10-27T01:00:00Z', 'lines' => []],
                ['document_id' => 'SO-LEGACY-0001', 'reserved_at' => '2026-10-27T01:00:00Z', 'lines' => []],
            ]]),
            '*/stocks/release/document' => Http::response(['success' => true, 'data' => []], $releaseStatus),
        ]);
        Invoice::create(['invoice_number' => 'INV-ACTIVE-271026', 'invoice_date' => '2026-10-27', 'created_by' => 'Test', 'warehouse_status' => 'PENDING_WAREHOUSE']);
        Invoice::create(['invoice_number' => 'INV-CANCELLED-271026', 'invoice_date' => '2026-10-27', 'created_by' => 'Test', 'warehouse_status' => 'CANCELLED']);
    }

    /** @return list<string> */
    private function releasedNumbers(): array
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/stocks/release/document'))
            ->map(fn (array $pair) => $pair[0]['order_id'])
            ->values()
            ->all();
    }

    public function test_releases_stock_held_for_missing_or_cancelled_invoices_only(): void
    {
        $this->fakeInventory();

        $this->artisan('sales:reconcile-reservations')->assertSuccessful();

        $this->assertEqualsCanonicalizing(['INV-ORPHAN-271026', 'INV-CANCELLED-271026'], $this->releasedNumbers());
        // Không có người dùng: gọi Inventory bằng khóa nội bộ
        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Internal-Service-Key', 'internal-test-key')
            && ! $request->hasHeader('Authorization'));
    }

    public function test_dry_run_releases_nothing(): void
    {
        $this->fakeInventory();

        $this->artisan('sales:reconcile-reservations', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame([], $this->releasedNumbers());
    }

    public function test_reports_failure_when_release_fails_so_it_is_retried_next_run(): void
    {
        $this->fakeInventory(releaseStatus: 503);

        $this->artisan('sales:reconcile-reservations')->assertFailed();
    }

    public function test_is_scheduled_every_five_minutes(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('sales:reconcile-reservations')->assertSuccessful();
    }
}
