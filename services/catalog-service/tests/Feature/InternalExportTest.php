<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalExportTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'phpunit-internal-key';

    public function test_export_requires_internal_key(): void
    {
        $this->getJson('/api/internal/exports/products')->assertForbidden();
        $this->withHeaders(['X-Internal-Service-Key' => 'wrong'])->getJson('/api/internal/exports/products')->assertForbidden();
    }

    public function test_products_export_pages_by_id_and_snapshots_names(): void
    {
        $brandId = DB::table('brands')->insertGetId(['name' => 'Daikin', 'created_at' => now(), 'updated_at' => now()]);
        $catId = DB::table('categories')->insertGetId(['code' => 'dieu-hoa', 'name' => 'Điều hòa', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['A-1', 'A-2', 'A-3'] as $sku) {
            DB::table('products')->insert(['sku' => $sku, 'designation' => 'SP '.$sku, 'brand_id' => $brandId, 'category_id' => $catId,
                'gross_price' => 100, 'cost_price' => 60, 'min_stock' => 2, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        }

        $first = $this->withHeaders(['X-Internal-Service-Key' => self::KEY])
            ->getJson('/api/internal/exports/products?limit=2')->assertOk()->json('data');

        $this->assertSame(['A-1', 'A-2'], array_column($first['items'], 'sku'));
        $this->assertSame('Daikin', $first['items'][0]['brand']);
        $this->assertSame('Điều hòa', $first['items'][0]['category']);
        $this->assertSame($first['items'][1]['id'], $first['next_after_id']);

        $second = $this->withHeaders(['X-Internal-Service-Key' => self::KEY])
            ->getJson('/api/internal/exports/products?limit=2&after_id='.$first['next_after_id'])->assertOk()->json('data');

        $this->assertSame(['A-3'], array_column($second['items'], 'sku'));
        $this->assertNull($second['next_after_id']);
    }
}
