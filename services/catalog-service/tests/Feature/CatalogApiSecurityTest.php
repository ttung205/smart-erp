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
}
