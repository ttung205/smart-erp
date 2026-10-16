<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_route_rejects_missing_token(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    public function test_protected_route_rejects_invalid_signature(): void
    {
        config(['services.identity.jwt_secret' => 'correct-secret']);

        $this->withHeader('Authorization', 'Bearer aaa.bbb.ccc')
            ->getJson('/api/orders')
            ->assertUnauthorized();
    }

    public function test_permission_is_required_for_protected_action(): void
    {
        $this->withHeaders($this->jwtHeaders(['order.view']))
            ->postJson('/api/orders', [])
            ->assertForbidden();
    }

    public function test_sales_invoice_requires_its_own_create_permission(): void
    {
        $this->withHeaders($this->jwtHeaders(['invoice.create']))
            ->postJson('/api/invoices', [])
            ->assertForbidden();

        $this->withHeaders($this->jwtHeaders(['sale-invoice.create']))
            ->postJson('/api/invoices', [])
            ->assertUnprocessable();
    }

    public function test_super_admin_bypasses_individual_permissions(): void
    {
        $this->withHeaders($this->jwtHeaders([], ['SUPER_ADMIN']))
            ->getJson('/api/orders')
            ->assertOk();
    }
}
