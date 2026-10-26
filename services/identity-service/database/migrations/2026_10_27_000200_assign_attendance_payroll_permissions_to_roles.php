<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phân quyền mặc định (chưa có phòng nhân sự riêng): kế toán trưởng lập công/lương và chi lương (cần thêm finance.approve để ghi sổ),
     * Super Admin (giám đốc) duyệt. Điều chỉnh tại màn hình phân quyền khi công ty có bộ phận HR.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'FINANCE_ACCOUNTING_MANAGER' => ['attendance.view', 'attendance.manage', 'payroll.view', 'payroll.manage', 'payroll.pay'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            foreach ($permissionCodes as $code) {
                $permissionId = DB::table('permissions')->where('code', $code)->value('id');
                if ($roleId && $permissionId) {
                    DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            $permissionIds = DB::table('permissions')->whereIn('code', $permissionCodes)->pluck('id');
            DB::table('role_permissions')->where('role_id', $roleId)->whereIn('permission_id', $permissionIds)->delete();
        }
    }
};
