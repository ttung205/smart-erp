<?php

namespace Tests\Feature;

use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UsersByPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // notification-service gọi endpoint này bằng khóa nội bộ, không có token người dùng
        $this->withHeader('X-Internal-Service-Key', 'phpunit-internal-key');
    }

    public function test_returns_active_users_holding_the_permission_or_module_manage(): void
    {
        $this->seed(IdentitySeeder::class);

        $expected = DB::table('users')
            ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('permissions.code', ['quotation.approve', 'quotation.manage'])
            ->where('users.is_active', true)
            ->distinct()
            ->pluck('users.id')
            ->sort()
            ->values()
            ->all();

        $response = $this->getJson('/api/users/by-permission?permissions=quotation.approve')->assertOk();

        $this->assertNotEmpty($expected);
        $this->assertSame($expected, collect($response->json('data'))->pluck('id')->sort()->values()->all());
    }

    public function test_inactive_users_are_excluded(): void
    {
        $this->seed(IdentitySeeder::class);
        $userId = $this->getJson('/api/users/by-permission?permissions=order.approve')->json('data.0.id');
        DB::table('users')->where('id', $userId)->update(['is_active' => false]);

        $ids = collect($this->getJson('/api/users/by-permission?permissions=order.approve')->json('data'))->pluck('id');

        $this->assertNotContains($userId, $ids);
    }

    public function test_permissions_parameter_is_required(): void
    {
        $this->getJson('/api/users/by-permission')->assertStatus(422);
    }
}
