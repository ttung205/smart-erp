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
        DB::table('permissions')->updateOrInsert(['code' => 'finance.create'], [
            'module' => 'finance', 'description' => 'Lập chứng từ và yêu cầu điều chỉnh hóa đơn',
        ]);
        $permissionId = DB::table('permissions')->where('code', 'finance.create')->value('id');
        $roleIds = DB::table('roles')->whereIn('code', ['FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER', 'SUPER_ADMIN'])->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'finance.create')->value('id');
        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
