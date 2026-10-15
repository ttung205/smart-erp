<?php

namespace Tests\Feature;

use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceCreatePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_roles_can_create_adjustment_requests_but_only_manager_approves(): void
    {
        $this->seed(IdentitySeeder::class);
        $roles = DB::table('roles')->whereIn('code', ['FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER'])
            ->pluck('id', 'code');
        $createPermission = DB::table('permissions')->where('code', 'finance.create')->value('id');
        $approvePermission = DB::table('permissions')->where('code', 'finance.approve')->value('id');

        $this->assertTrue(DB::table('role_permissions')->where('role_id', $roles['FINANCE_ACCOUNTING'])
            ->where('permission_id', $createPermission)->exists());
        $this->assertFalse(DB::table('role_permissions')->where('role_id', $roles['FINANCE_ACCOUNTING'])
            ->where('permission_id', $approvePermission)->exists());
        $this->assertTrue(DB::table('role_permissions')->where('role_id', $roles['FINANCE_ACCOUNTING_MANAGER'])
            ->where('permission_id', $approvePermission)->exists());
    }
}
