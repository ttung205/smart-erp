<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Thêm quyền chat.use (chat-service kiểm tra quyền này trên mọi endpoint) và cấp cho mọi vai trò.
// Người dùng cần đăng nhập lại để JWT mang quyền mới.
return new class extends Migration
{
    public function up(): void
    {
        $now = now()->utc();
        DB::table('permissions')->insertOrIgnore([
            'code' => 'chat.use',
            'module' => 'chat',
            'description' => 'Sử dụng chat nội bộ (nhắn tin, nhóm, luồng công việc)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('code', 'chat.use')->value('id');
        if (! $permissionId) {
            return;
        }

        foreach (DB::table('roles')->pluck('id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'chat.use')->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
