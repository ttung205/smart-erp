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

    public function test_demo_roles_have_separate_partner_and_sales_invoice_permissions(): void
    {
        $this->seed(IdentitySeeder::class);
        $permissions = DB::table('roles')
            ->join('role_permissions', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->select('roles.code as role_code', 'permissions.code as permission_code')
            ->get()->groupBy('role_code')->map(fn ($rows) => $rows->pluck('permission_code')->all());

        $this->assertContains('customer.view', $permissions['SALE']);
        $this->assertNotContains('supplier.view', $permissions['SALE']);
        $this->assertNotContains('sale-invoice.create', $permissions['SALE_MANAGER']);
        $this->assertNotContains('invoice.view', $permissions['SALE_MANAGER']);

        $this->assertContains('purchase.view', $permissions['PURCHASE']);
        $this->assertContains('supplier.view', $permissions['PURCHASE']);
        $this->assertNotContains('customer.view', $permissions['PURCHASE']);

        $this->assertContains('sale-invoice.create', $permissions['FINANCE_ACCOUNTING']);
        $this->assertContains('customer.view', $permissions['FINANCE_ACCOUNTING']);
        $this->assertContains('supplier.view', $permissions['FINANCE_ACCOUNTING']);

        $this->assertContains('inventory.view', $permissions['LOGISTIC']);
        $this->assertNotContains('customer.view', $permissions['LOGISTIC']);
        $this->assertNotContains('supplier.view', $permissions['LOGISTIC']);
    }
}
