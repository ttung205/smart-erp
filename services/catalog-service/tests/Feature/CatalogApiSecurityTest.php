<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_is_public(): void
    {
        $this->getJson('/api/health')->assertOk();
    }

    public function test_routes_require_token(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer aaa.bbb.ccc')->getJson('/api/brands')->assertUnauthorized();
    }

    public function test_any_signed_in_user_can_read(): void
    {
        $this->withHeaders($this->jwtHeaders())->getJson('/api/products')->assertOk();
    }

    public function test_writes_require_permission(): void
    {
        $this->withHeaders($this->jwtHeaders(['catalog.view']))
            ->postJson('/api/brands', ['name' => 'ACME'])
            ->assertForbidden();

        $this->withHeaders($this->jwtHeaders(['catalog.create']))
            ->postJson('/api/brands', ['name' => 'ACME'])
            ->assertCreated();
    }

    public function test_price_index_hidden_from_non_purchase_roles(): void
    {
        DB::table('brands')->insert(['name' => 'ACME', 'price_index' => 1.2, 'created_at' => now(), 'updated_at' => now()]);

        $this->withHeaders($this->jwtHeaders([], ['SALE']))->getJson('/api/brands')
            ->assertOk()
            ->assertJsonMissingPath('data.0.price_index');

        $this->withHeaders($this->jwtHeaders([], ['PURCHASE']))->getJson('/api/brands')
            ->assertJsonPath('data.0.price_index', 1.2);
    }

    public function test_apply_inquiry_result_only_via_internal_service(): void
    {
        $purchaseUser = $this->jwtHeaders([], ['PURCHASE']);

        $this->withHeaders($purchaseUser)->postJson('/api/products/apply-inquiry-result', [])->assertForbidden();

        $this->withHeaders([...$purchaseUser, 'X-Internal-Service-Key' => 'phpunit-internal-key'])
            ->postJson('/api/products/apply-inquiry-result', [])
            ->assertUnprocessable();
    }

    public function test_internal_service_can_batch_read_products_including_inactive(): void
    {
        $row = ['designation' => 'X', 'gross_price' => 100, 'cost_price' => 60, 'min_stock' => 0, 'created_at' => now(), 'updated_at' => now()];
        $active = DB::table('products')->insertGetId([...$row, 'sku' => 'A-1', 'status' => 'ACTIVE']);
        $inactive = DB::table('products')->insertGetId([...$row, 'sku' => 'B-2', 'status' => 'INACTIVE']);
        DB::table('products')->insert([...$row, 'sku' => 'C-3', 'status' => 'ACTIVE']);
        $internal = ['X-Internal-Service-Key' => 'phpunit-internal-key'];

        $this->getJson('/api/products?ids='.$active)->assertUnauthorized();
        // Chứng từ cũ có thể tham chiếu sản phẩm đã ngừng bán; giá vốn vẫn ẩn với service không có quyền
        $this->withHeaders($internal)->getJson("/api/products?ids={$active},{$inactive}")
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonMissingPath('data.0.cost_price');
        $this->withHeaders($internal)->getJson('/api/products?skus=C-3')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.sku', 'C-3');
        $this->withHeaders(['X-Internal-Service-Key' => 'wrong'])->getJson('/api/products')->assertUnauthorized();
    }
}
