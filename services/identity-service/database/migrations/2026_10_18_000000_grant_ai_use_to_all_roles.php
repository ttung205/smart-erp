<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trợ lý AI dùng cho toàn công ty: mọi vai trò được mở widget.
 * Dữ liệu từng người xem được vẫn giới hạn theo quyền nghiệp vụ (order.view, finance.view...) tại ai-service.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'ai.use')->value('id');
        if (!$permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => 'ai.use',
                'module' => 'ai',
                'description' => 'Sử dụng trợ lý AI phân tích dữ liệu',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach (DB::table('roles')->pluck('id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'ai.use')->value('id');
        $superAdminId = DB::table('roles')->where('code', 'SUPER_ADMIN')->value('id');
        DB::table('role_permissions')
            ->where('permission_id', $permissionId)
            ->where('role_id', '!=', $superAdminId)
            ->delete();
    }
};
