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
        $rolePermissions = [
            'FINANCE_ACCOUNTING' => ['finance.view', 'master.view'],
            'FINANCE_ACCOUNTING_MANAGER' => ['finance.view', 'finance.approve', 'invoice.view', 'invoice.manage', 'master.view'],
        ];

        foreach ($rolePermissions as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            $permissionIds = DB::table('permissions')->whereIn('code', $permissionCodes)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleIds = DB::table('roles')->whereIn('code', ['FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER'])->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('code', ['finance.view', 'finance.approve', 'invoice.view', 'invoice.manage', 'master.view'])->pluck('id');
        DB::table('role_permissions')->whereIn('role_id', $roleIds)->whereIn('permission_id', $permissionIds)->delete();
    }
};
