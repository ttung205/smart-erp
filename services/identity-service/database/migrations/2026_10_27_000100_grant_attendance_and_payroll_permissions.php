<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'attendance.view' => 'Xem bảng công toàn công ty',
        'attendance.manage' => 'Quản lý chấm công: nhập file máy chấm công, thêm/xóa công, khóa kỳ công',
        'payroll.view' => 'Xem bảng lương và phiếu lương toàn công ty',
        'payroll.manage' => 'Lập và tính bảng lương, sửa thông tin lương nhân viên',
        'payroll.approve' => 'Duyệt bảng lương',
        'payroll.pay' => 'Chi lương và hạch toán chi lương',
    ];

    /**
     * Run the migrations.
     * SUPER_ADMIN nhận đủ quyền; các vai trò khác được cấp theo nhu cầu ở màn hình phân quyền.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'SUPER_ADMIN')->value('id');
        foreach (self::PERMISSIONS as $code => $description) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['module' => 'eoffice', 'description' => $description]);
            if ($roleId) {
                $permissionId = DB::table('permissions')->where('code', $code)->value('id');
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('code', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
