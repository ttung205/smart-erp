<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Departments (Phòng ban nghiệp vụ)
        $depts = [
            ['code' => 'BOD', 'name' => 'Ban Giám Đốc / Điều Hành', 'description' => 'Toàn quyền điều hành và quản trị hệ thống.'],
            ['code' => 'SALES', 'name' => 'Phòng Kinh Doanh', 'description' => 'Quản lý báo giá, đơn bán hàng B2B và khách hàng.'],
            ['code' => 'PURCHASE', 'name' => 'Phòng Mua Hàng & Cung Ứng', 'description' => 'Quản lý đơn mua PO, nhà cung cấp và giá vốn.'],
            ['code' => 'TECHNICAL', 'name' => 'Phòng Kỹ Thuật & Công Nghệ', 'description' => 'Bảo trì thiết bị, tích hợp hệ thống và hạ tầng IT.'],
            ['code' => 'FINANCE', 'name' => 'Phòng Tài Chính Kế Toán', 'description' => 'Hóa đơn, công nợ, dòng tiền và kiểm toán.'],
            ['code' => 'LOGISTIC', 'name' => 'Bộ Phận Kho Vận & Điều Vận', 'description' => 'Kiểm soát xuất nhập tồn và điều phối vận tải.'],
        ];
        foreach ($depts as $d) {
            DB::table('departments')->updateOrInsert(['code' => $d['code']], $d);
        }

        // 2. Permissions (Toàn bộ quyền hệ thống)
        $perms = [
            ['code' => 'sales.view', 'module' => 'sales', 'description' => 'Xem danh sách và chi tiết đơn bán hàng SO'],
            ['code' => 'sales.create', 'module' => 'sales', 'description' => 'Tạo mới báo giá và đơn đặt hàng SO'],
            ['code' => 'sales.update', 'module' => 'sales', 'description' => 'Cập nhật thông tin đơn bán hàng SO'],
            ['code' => 'sales.cancel', 'module' => 'sales', 'description' => 'Hủy đơn đặt hàng SO'],
            ['code' => 'sales.approve', 'module' => 'sales', 'description' => 'Phê duyệt chiết khấu hoặc đơn bán hàng'],
            ['code' => 'delivery.create', 'module' => 'sales', 'description' => 'Tạo yêu cầu giao hàng Delivery Request'],

            ['code' => 'purchase.view', 'module' => 'purchase', 'description' => 'Xem danh sách và chi tiết đơn mua hàng PO'],
            ['code' => 'purchase.create', 'module' => 'purchase', 'description' => 'Tạo mới đơn mua hàng PO'],
            ['code' => 'purchase.approve', 'module' => 'purchase', 'description' => 'Phê duyệt đơn mua hàng PO'],

            ['code' => 'inventory.view', 'module' => 'inventory', 'description' => 'Xem tồn kho On-Hand, Reserved, Available'],
            ['code' => 'inventory.receive', 'module' => 'inventory', 'description' => 'Nhập kho thực tế Goods Receipt'],
            ['code' => 'inventory.issue', 'module' => 'inventory', 'description' => 'Xuất kho thực tế Goods Issue'],
            ['code' => 'inventory.transfer', 'module' => 'inventory', 'description' => 'Điều chuyển kho Stock Transfer'],

            ['code' => 'catalog.view', 'module' => 'catalog', 'description' => 'Xem danh mục sản phẩm và đối tác'],
            ['code' => 'catalog.create', 'module' => 'catalog', 'description' => 'Tạo mới sản phẩm hoặc đối tác'],
            ['code' => 'catalog.edit', 'module' => 'catalog', 'description' => 'Chỉnh sửa sản phẩm và bảng giá'],

            ['code' => 'finance.view', 'module' => 'finance', 'description' => 'Xem báo cáo tài chính và công nợ'],
            ['code' => 'finance.approve', 'module' => 'finance', 'description' => 'Duyệt thanh toán và xuất hóa đơn'],

            ['code' => 'technical.manage', 'module' => 'technical', 'description' => 'Cấu hình kỹ thuật và giám sát microservices'],
            ['code' => 'report.view', 'module' => 'analytics', 'description' => 'Xem Dashboard báo cáo tổng quan'],
            ['code' => 'ai.use', 'module' => 'ai', 'description' => 'Sử dụng trợ lý AI Copilot phân tích dữ liệu'],

            ['code' => 'users.view', 'module' => 'admin', 'description' => 'Xem danh sách tài khoản'],
            ['code' => 'users.manage', 'module' => 'admin', 'description' => 'Quản trị tài khoản và ma trận phân quyền'],
        ];
        foreach ($perms as $p) {
            DB::table('permissions')->updateOrInsert(['code' => $p['code']], $p);
        }

        // 3. Các Roles theo đúng yêu cầu chuẩn hóa
        $roles = [
            ['code' => 'SUPER_ADMIN', 'name' => 'Super Admin', 'description' => 'Toàn quyền kiểm soát và vận hành toàn bộ hệ thống Smart ERP'],
            ['code' => 'SALE', 'name' => 'Sale', 'description' => 'Chuyên viên kinh doanh (Chưa phân quyền)'],
            ['code' => 'SALE_MANAGER', 'name' => 'Sale Manager', 'description' => 'Trưởng phòng kinh doanh (Chưa phân quyền)'],
            ['code' => 'PURCHASE', 'name' => 'Purchase', 'description' => 'Chuyên viên mua hàng (Chưa phân quyền)'],
            ['code' => 'PURCHASE_MANAGER', 'name' => 'Purchase Manager', 'description' => 'Trưởng phòng mua hàng (Chưa phân quyền)'],
            ['code' => 'TECHNICAL', 'name' => 'Technical', 'description' => 'Kỹ thuật viên hệ thống (Chưa phân quyền)'],
            ['code' => 'TECHNICAL_MANAGER', 'name' => 'Technical Manager', 'description' => 'Trưởng phòng kỹ thuật (Chưa phân quyền)'],
            ['code' => 'FINANCE_ACCOUNTING', 'name' => 'Finance & Accounting', 'description' => 'Chuyên viên tài chính kế toán (Chưa phân quyền)'],
            ['code' => 'FINANCE_ACCOUNTING_MANAGER', 'name' => 'Finance & Accounting Manager', 'description' => 'Kế toán trưởng / Quản lý tài chính (Chưa phân quyền)'],
            ['code' => 'LOGISTIC', 'name' => 'Logistic', 'description' => 'Chuyên viên kho vận & điều vận (Chưa phân quyền)'],
            ['code' => 'LOGISTIC_MANAGER', 'name' => 'Logistic Manager', 'description' => 'Trưởng phòng kho vận & vận tải (Chưa phân quyền)'],
        ];

        // Xóa bảng phân quyền cũ để chuẩn hóa sạch sẽ
        DB::table('role_permissions')->truncate();

        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(['code' => $r['code']], $r);
        }

        // 4. Map Permissions: CHỈ SUPER_ADMIN CÓ FULL QUYỀN, TẤT CẢ CÁC ROLE CÒN LẠI ĐỀU CHƯA PHÂN QUYỀN (RỖNG)
        $allPerms = DB::table('permissions')->pluck('id', 'code');
        $superAdminRoleId = DB::table('roles')->where('code', 'SUPER_ADMIN')->value('id');

        if ($superAdminRoleId) {
            foreach ($allPerms as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $superAdminRoleId,
                    'permission_id' => $permId
                ]);
            }
        }

        // 5. Tạo đúng 1 tài khoản mẫu cho từng Role (Mật khẩu mặc định: password123)
        $defaultHash = Hash::make('password123');
        $allDepts = DB::table('departments')->pluck('id', 'code');
        $allRoles = DB::table('roles')->pluck('id', 'code');

        $users = [
            [
                'email' => 'admin@erp.local',
                'name' => 'Quản Trị Viên Tối Cao (Super Admin)',
                'dept' => 'BOD',
                'role' => 'SUPER_ADMIN'
            ],
            [
                'email' => 'sale@erp.local',
                'name' => 'Chuyên Viên Kinh Doanh (Sale)',
                'dept' => 'SALES',
                'role' => 'SALE'
            ],
            [
                'email' => 'sale.manager@erp.local',
                'name' => 'Trưởng Phòng Kinh Doanh (Sale Manager)',
                'dept' => 'SALES',
                'role' => 'SALE_MANAGER'
            ],
            [
                'email' => 'purchase@erp.local',
                'name' => 'Chuyên Viên Mua Hàng (Purchase)',
                'dept' => 'PURCHASE',
                'role' => 'PURCHASE'
            ],
            [
                'email' => 'purchase.manager@erp.local',
                'name' => 'Trưởng Phòng Mua Hàng (Purchase Manager)',
                'dept' => 'PURCHASE',
                'role' => 'PURCHASE_MANAGER'
            ],
            [
                'email' => 'technical@erp.local',
                'name' => 'Kỹ Thuật Viên Hệ Thống (Technical)',
                'dept' => 'TECHNICAL',
                'role' => 'TECHNICAL'
            ],
            [
                'email' => 'technical.manager@erp.local',
                'name' => 'Trưởng Phòng Kỹ Thuật (Technical Manager)',
                'dept' => 'TECHNICAL',
                'role' => 'TECHNICAL_MANAGER'
            ],
            [
                'email' => 'finance@erp.local',
                'name' => 'Chuyên Viên Tài Chính Kế Toán (Finance)',
                'dept' => 'FINANCE',
                'role' => 'FINANCE_ACCOUNTING'
            ],
            [
                'email' => 'finance.manager@erp.local',
                'name' => 'Kế Toán Trưởng (Finance Manager)',
                'dept' => 'FINANCE',
                'role' => 'FINANCE_ACCOUNTING_MANAGER'
            ],
            [
                'email' => 'logistic@erp.local',
                'name' => 'Chuyên Viên Kho Vận (Logistic)',
                'dept' => 'LOGISTIC',
                'role' => 'LOGISTIC'
            ],
            [
                'email' => 'logistic.manager@erp.local',
                'name' => 'Trưởng Phòng Kho Vận (Logistic Manager)',
                'dept' => 'LOGISTIC',
                'role' => 'LOGISTIC_MANAGER'
            ],
        ];

        // Clean user_roles table and remove any old users not in the seeder list
        $validEmails = array_column($users, 'email');
        $oldUserIds = DB::table('users')->whereNotIn('email', $validEmails)->pluck('id');
        DB::table('user_roles')->whereIn('user_id', $oldUserIds)->delete();
        DB::table('users')->whereNotIn('email', $validEmails)->delete();
        DB::table('user_roles')->truncate();

        foreach ($users as $u) {
            $deptId = $u['dept'] ? ($allDepts[$u['dept']] ?? null) : null;
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password_hash' => $defaultHash,
                    'department_id' => $deptId,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            $userId = DB::table('users')->where('email', $u['email'])->value('id');
            $roleId = $allRoles[$u['role']] ?? null;
            if ($userId && $roleId) {
                DB::table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => $roleId
                ]);
            }
        }
    }
}