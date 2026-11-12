<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InternalReceivablesExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_receivables_export_requires_internal_key(): void
    {
        $this->getJson('/api/internal/exports/receivables')->assertForbidden();
    }

    public function test_receivables_export_pages_with_outstanding_amounts(): void
    {
        // Chỉ kiểm tra xuất dữ liệu: bỏ qua FK tới sổ tài khoản (không liên quan tới export)
        Schema::disableForeignKeyConstraints();
        foreach (['INV-1', 'INV-2'] as $i => $number) {
            DB::table('customer_receivables')->insert([
                'source_key' => 'k-'.$i, 'customer_id' => 7, 'customer_name' => 'Khách A', 'invoice_number' => $number,
                'invoice_date' => '2026-10-01', 'due_date' => '2026-10-31', 'account_id' => 1,
                'original_amount' => 1000, 'paid_amount' => 300, 'outstanding_amount' => 700, 'status' => 'OPEN',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Schema::enableForeignKeyConstraints();

        $data = $this->withHeaders(['X-Internal-Service-Key' => 'phpunit-internal-key'])
            ->getJson('/api/internal/exports/receivables?limit=1')->assertOk()->json('data');

        $this->assertSame('INV-1', $data['items'][0]['invoice_number']);
        $this->assertSame(700.0, (float) $data['items'][0]['outstanding_amount']);
        $this->assertSame($data['items'][0]['id'], $data['next_after_id']);
    }
}
