<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ([
            ['code' => 'customer.view', 'module' => 'catalog', 'description' => 'Xem danh sách khách hàng'],
            ['code' => 'supplier.view', 'module' => 'catalog', 'description' => 'Xem danh sách nhà cung cấp'],
            ['code' => 'sale-invoice.create', 'module' => 'invoice', 'description' => 'Lập chứng từ bán hàng từ yêu cầu giao hàng'],
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(['code' => $permission['code']], $permission);
        }

        $grants = [
            'SUPER_ADMIN' => ['customer.view', 'supplier.view', 'sale-invoice.create'],
            'SALE' => ['catalog.view', 'customer.view'],
            'SALE_MANAGER' => ['catalog.view', 'customer.view'],
            'PURCHASE' => ['purchase.view', 'purchase.create', 'catalog.view', 'supplier.view', 'inventory.view'],
            'PURCHASE_MANAGER' => ['purchase.view', 'purchase.create', 'purchase.approve', 'catalog.view', 'supplier.view', 'inventory.view'],
            'TECHNICAL' => ['catalog.view', 'customer.view', 'supplier.view'],
            'TECHNICAL_MANAGER' => ['catalog.view', 'customer.view', 'supplier.view'],
            'LOGISTIC' => ['catalog.view', 'invoice.view', 'invoice.manage'],
            'LOGISTIC_MANAGER' => ['catalog.view', 'invoice.view', 'invoice.manage'],
            'FINANCE_ACCOUNTING' => ['catalog.view', 'customer.view', 'supplier.view', 'delivery.view', 'invoice.create', 'sale-invoice.create'],
            'FINANCE_ACCOUNTING_MANAGER' => ['catalog.view', 'customer.view', 'supplier.view', 'delivery.view', 'invoice.create', 'sale-invoice.create'],
        ];
        $roleIds = DB::table('roles')->pluck('id', 'code');
        $permissionIds = DB::table('permissions')->pluck('id', 'code');
        foreach ($grants as $roleCode => $codes) {
            foreach ($codes as $code) {
                if (isset($roleIds[$roleCode], $permissionIds[$code])) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $roleIds[$roleCode], 'permission_id' => $permissionIds[$code],
                    ]);
                }
            }
        }

        foreach (['SALE' => ['invoice.view'], 'SALE_MANAGER' => ['invoice.view', 'invoice.create', 'invoice.manage']] as $roleCode => $codes) {
            if (isset($roleIds[$roleCode])) {
                DB::table('role_permissions')->where('role_id', $roleIds[$roleCode])
                    ->whereIn('permission_id', $permissionIds->only($codes)->values())->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'code');
        $permissionIds = DB::table('permissions')->pluck('id', 'code');
        foreach (['SALE' => ['invoice.view'], 'SALE_MANAGER' => ['invoice.view', 'invoice.create', 'invoice.manage']] as $roleCode => $codes) {
            foreach ($codes as $code) {
                if (isset($roleIds[$roleCode], $permissionIds[$code])) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $roleIds[$roleCode], 'permission_id' => $permissionIds[$code],
                    ]);
                }
            }
        }
        foreach (['customer.view', 'supplier.view', 'sale-invoice.create'] as $code) {
            if (isset($permissionIds[$code])) {
                DB::table('role_permissions')->where('permission_id', $permissionIds[$code])->delete();
                DB::table('permissions')->where('id', $permissionIds[$code])->delete();
            }
        }
    }
};
