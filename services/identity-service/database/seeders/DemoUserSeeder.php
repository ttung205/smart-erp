<?php

namespace Database\Seeders;

use App\Services\EmployeeCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Tài khoản mẫu cho máy dev và test (mật khẩu công khai password123, mã nhân viên cấp theo tiền tố ER).
 * KHÔNG nằm trong DatabaseSeeder và từ chối chạy ở production: dữ liệu thật tạo bằng
 * `php artisan identity:bootstrap-admin` rồi thêm nhân viên qua màn hình Nhân viên.
 * Seeder này chỉ thêm hoặc cập nhật 11 tài khoản mẫu, không xóa người dùng nào khác.
 *
 * Chạy: php artisan db:seed --class=DemoUserSeeder (cần IdentitySeeder chạy trước để có phòng ban và vai trò).
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoUserSeeder không được chạy ở production.');
        }

        $defaultHash = Hash::make('password123');
        $allDepts = DB::table('departments')->pluck('id', 'code');
        $allRoles = DB::table('roles')->pluck('id', 'code');

        $users = [
            ['email' => 'admin@erp.local', 'name' => 'Quản Trị Viên Tối Cao (Super Admin)', 'dept' => 'BOD', 'role' => 'SUPER_ADMIN'],
            ['email' => 'sale@erp.local', 'name' => 'Chuyên Viên Kinh Doanh (Sale)', 'dept' => 'SALES', 'role' => 'SALE'],
            ['email' => 'sale.manager@erp.local', 'name' => 'Trưởng Phòng Kinh Doanh (Sale Manager)', 'dept' => 'SALES', 'role' => 'SALE_MANAGER'],
            ['email' => 'purchase@erp.local', 'name' => 'Chuyên Viên Mua Hàng (Purchase)', 'dept' => 'PURCHASE', 'role' => 'PURCHASE'],
            ['email' => 'purchase.manager@erp.local', 'name' => 'Trưởng Phòng Mua Hàng (Purchase Manager)', 'dept' => 'PURCHASE', 'role' => 'PURCHASE_MANAGER'],
            ['email' => 'technical@erp.local', 'name' => 'Kỹ Thuật Viên Hệ Thống (Technical)', 'dept' => 'TECHNICAL', 'role' => 'TECHNICAL'],
            ['email' => 'technical.manager@erp.local', 'name' => 'Trưởng Phòng Kỹ Thuật (Technical Manager)', 'dept' => 'TECHNICAL', 'role' => 'TECHNICAL_MANAGER'],
            ['email' => 'finance@erp.local', 'name' => 'Chuyên Viên Tài Chính Kế Toán (Finance)', 'dept' => 'FINANCE', 'role' => 'FINANCE_ACCOUNTING'],
            ['email' => 'finance.manager@erp.local', 'name' => 'Kế Toán Trưởng (Finance Manager)', 'dept' => 'FINANCE', 'role' => 'FINANCE_ACCOUNTING_MANAGER'],
            ['email' => 'logistic@erp.local', 'name' => 'Chuyên Viên Kho Vận (Logistic)', 'dept' => 'LOGISTIC', 'role' => 'LOGISTIC'],
            ['email' => 'logistic.manager@erp.local', 'name' => 'Trưởng Phòng Kho Vận (Logistic Manager)', 'dept' => 'LOGISTIC', 'role' => 'LOGISTIC_MANAGER'],
        ];

        foreach ($users as $u) {
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password_hash' => $defaultHash,
                    'department_id' => $allDepts[$u['dept']] ?? null,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $userId = DB::table('users')->where('email', $u['email'])->value('id');
            $roleId = $allRoles[$u['role']] ?? null;
            if ($userId && $roleId) {
                DB::table('user_roles')->where('user_id', $userId)->delete();
                DB::table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
            }
        }

        // Trưởng phòng (người duyệt đơn E-Office của phòng)
        $departmentManagers = [
            'BOD' => 'admin@erp.local',
            'SALES' => 'sale.manager@erp.local',
            'PURCHASE' => 'purchase.manager@erp.local',
            'TECHNICAL' => 'technical.manager@erp.local',
            'FINANCE' => 'finance.manager@erp.local',
            'LOGISTIC' => 'logistic.manager@erp.local',
        ];
        foreach ($departmentManagers as $departmentCode => $email) {
            DB::table('departments')->where('code', $departmentCode)->update([
                'manager_id' => DB::table('users')->where('email', $email)->value('id'),
            ]);
        }

        // Quản lý trực tiếp: nhân viên → trưởng phòng của mình, trưởng phòng → Super Admin
        $adminId = DB::table('users')->where('email', 'admin@erp.local')->value('id');
        $managerIds = DB::table('departments')->whereNotNull('manager_id')->pluck('manager_id', 'id');
        $demoIds = DB::table('users')->whereIn('email', array_column($users, 'email'))->get(['id', 'department_id']);
        foreach ($demoIds as $user) {
            $lineManagerId = $managerIds->contains($user->id) ? $adminId : ($managerIds[$user->department_id] ?? null);
            DB::table('users')->where('id', $user->id)->update([
                'line_manager_id' => $lineManagerId !== $user->id ? $lineManagerId : null,
            ]);
        }

        // Mã nhân viên: cần tiền tố công ty, máy dev chưa cấu hình thì đặt ER.
        if (! DB::table('company_profiles')->where('id', 1)->exists()) {
            DB::table('company_profiles')->insert(['id' => 1, 'name' => 'Smart ERP', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('company_profiles')->where('id', 1)->whereNull('employee_code_prefix')->update(['employee_code_prefix' => 'ER']);
        app(EmployeeCodeService::class)->assignMissing();
    }
}
