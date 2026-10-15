<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->after('description')->constrained('users')->nullOnDelete();
        });

        DB::table('permissions')->updateOrInsert(['code' => 'eoffice.manage'], [
            'module' => 'eoffice', 'description' => 'Quản trị văn phòng số: loại nghỉ, ngày lễ, quỹ phép nhân viên',
        ]);
        $permissionId = DB::table('permissions')->where('code', 'eoffice.manage')->value('id');
        $roleId = DB::table('roles')->where('code', 'SUPER_ADMIN')->value('id');
        if ($permissionId && $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }

        // Gán trưởng phòng mặc định cho dữ liệu mẫu đã có sẵn
        $managers = [
            'BOD' => 'admin@erp.local',
            'SALES' => 'sale.manager@erp.local',
            'PURCHASE' => 'purchase.manager@erp.local',
            'TECHNICAL' => 'technical.manager@erp.local',
            'FINANCE' => 'finance.manager@erp.local',
            'LOGISTIC' => 'logistic.manager@erp.local',
        ];
        foreach ($managers as $departmentCode => $email) {
            $userId = DB::table('users')->where('email', $email)->value('id');
            if ($userId) {
                DB::table('departments')->where('code', $departmentCode)->update(['manager_id' => $userId]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'eoffice.manage')->value('id');
        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
        });
    }
};
