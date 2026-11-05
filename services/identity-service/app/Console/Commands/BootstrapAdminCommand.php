<?php

namespace App\Console\Commands;

use App\Services\EmployeeCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Tạo tài khoản Super Admin đầu tiên cho hệ thống mới (production không còn tài khoản mẫu).
 * Mật khẩu lấy từ biến môi trường IDENTITY_ADMIN_PASSWORD hoặc hỏi trực tiếp, không nhận qua tham số dòng lệnh.
 */
class BootstrapAdminCommand extends Command
{
    protected $signature = 'identity:bootstrap-admin
        {--email= : Email của quản trị viên (nhận OTP đăng nhập)}
        {--name= : Họ tên quản trị viên}
        {--prefix= : Tiền tố mã nhân viên (tối đa 2 chữ cái), chỉ cần khi công ty chưa cấu hình}';

    protected $description = 'Tạo Super Admin đầu tiên và tiền tố mã nhân viên';

    public function handle(EmployeeCodeService $codes): int
    {
        $email = trim((string) $this->option('email'));
        $name = trim((string) $this->option('name'));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $name === '') {
            $this->error('Cần --email hợp lệ và --name.');

            return self::FAILURE;
        }

        $roleId = DB::table('roles')->where('code', 'SUPER_ADMIN')->value('id');
        if (! $roleId) {
            $this->error('Chưa có vai trò SUPER_ADMIN. Chạy `php artisan db:seed --force` (IdentitySeeder) trước.');

            return self::FAILURE;
        }
        if (DB::table('users')->where('email', $email)->exists()) {
            $this->error("Email {$email} đã tồn tại.");

            return self::FAILURE;
        }

        $prefix = strtoupper(trim((string) $this->option('prefix')));
        if ($codes->prefix() === null) {
            if (! preg_match('/^[A-Z]{1,2}$/', $prefix)) {
                $this->error('Công ty chưa có tiền tố mã nhân viên: cần --prefix gồm 1-2 chữ cái, ví dụ AC.');

                return self::FAILURE;
            }
            $this->saveCompanyPrefix($prefix);
        }

        $password = (string) (getenv('IDENTITY_ADMIN_PASSWORD') ?: '');
        if ($password === '' && $this->input->isInteractive()) {
            $password = (string) $this->secret('Mật khẩu quản trị viên (tối thiểu 14 ký tự)');
        }
        if (mb_strlen($password) < 14) {
            $this->error('Mật khẩu quá ngắn (tối thiểu 14 ký tự). Đặt biến IDENTITY_ADMIN_PASSWORD hoặc chạy có tương tác.');

            return self::FAILURE;
        }

        $userId = DB::transaction(function () use ($email, $name, $password, $roleId): int {
            $userId = (int) DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'password_hash' => Hash::make($password),
                'department_id' => DB::table('departments')->where('code', 'BOD')->value('id'),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);

            return $userId;
        });

        $codes->assignMissing();
        $code = DB::table('users')->where('id', $userId)->value('employee_code');
        $this->info("Đã tạo Super Admin {$email}, mã nhân viên {$code}.");

        return self::SUCCESS;
    }

    private function saveCompanyPrefix(string $prefix): void
    {
        $query = DB::table('company_profiles')->where('id', 1);
        if ($query->exists()) {
            $query->update(['employee_code_prefix' => $prefix, 'updated_at' => now()]);

            return;
        }
        DB::table('company_profiles')->insert([
            'id' => 1,
            'name' => 'Smart ERP',
            'employee_code_prefix' => $prefix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
