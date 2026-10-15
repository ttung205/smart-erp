<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rolePermissions = [
            'LOGISTIC' => [
                'inventory.view',
                'inventory.receive',
                'inventory.issue',
                'inventory.transfer',
                'sale_return.view',
                'sale_return.approve',
            ],
            'LOGISTIC_MANAGER' => [
                'inventory.view',
                'inventory.manage',
                'inventory.receive',
                'inventory.issue',
                'inventory.transfer',
                'sale_return.view',
                'sale_return.approve',
                'sale_return.post',
            ],
        ];

        foreach ($rolePermissions as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if (!$roleId) continue;
            $permissionIds = DB::table('permissions')->whereIn('code', $permissionCodes)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')->whereIn('code', ['LOGISTIC', 'LOGISTIC_MANAGER'])->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('code', [
            'inventory.view', 'inventory.manage', 'inventory.receive', 'inventory.issue', 'inventory.transfer',
            'sale_return.view', 'sale_return.approve', 'sale_return.post'
        ])->pluck('id');
        DB::table('role_permissions')->whereIn('role_id', $roleIds)->whereIn('permission_id', $permissionIds)->delete();
    }
};
