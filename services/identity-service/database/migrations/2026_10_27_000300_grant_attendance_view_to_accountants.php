<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kế toán cần xem toàn bộ dữ liệu chấm công (bảng công, yêu cầu, nhật ký điều chỉnh) để đối chiếu khi tính lương.
     * attendance.view chỉ cho xem, không cho sửa; không kèm quyền xem lương (payroll.view).
     */
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'attendance.view')->value('id');
        $roleId = DB::table('roles')->where('code', 'FINANCE_ACCOUNTING')->value('id');
        if ($permissionId && $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'attendance.view')->value('id');
        $roleId = DB::table('roles')->where('code', 'FINANCE_ACCOUNTING')->value('id');
        DB::table('role_permissions')->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
    }
};
