<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        // Departments
        $depts = [
            ['code' => 'SALES', 'name' => 'Phòng Kinh Doanh & Bán Hàng', 'description' => 'Quản lý báo giá, SO, khách hàng B2B.'],
            ['code' => 'PURCHASE', 'name' => 'Phòng Mua Hàng & Cung Ứng', 'description' => 'Quản lý PO, nhà cung cấp, tiếp nhận hàng.'],
            ['code' => 'WAREHOUSE', 'name' => 'Bộ Phận Kho Vận & Quản Lý Tồn', 'description' => 'Kiểm soát On-Hand/Reserved, nhập xuất kho.'],
            ['code' => 'BOD', 'name' => 'Ban Lãnh Đạo / Ban Giám Đốc', 'description' => 'Giám sát điều hành, phê duyệt và AI Copilot.'],
        ];
        foreach ($depts as $d) {
            DB::table('departments')->updateOrInsert(['code' => $d['code']], $d);
        }

        // Permissions
        $perms = [
            ['code' => 'sales.view', 'module' => 'sales', 'description' => 'Xem danh sách và chi tiết SO'],
            ['code' => 'sales.create', 'module' => 'sales', 'description' => 'Tạo mới báo giá và đơn đặt hàng SO'],
            ['code' => 'sales.update', 'module' => 'sales', 'description' => 'Cập nhật đơn đặt hàng SO'],
            ['code' => 'sales.cancel', 'module' => 'sales', 'description' => 'Hủy đơn đặt hàng SO'],
            ['code' => 'sales.approve', 'module' => 'sales', 'description' => 'Phê duyệt chiết khấu hoặc đơn bán hàng'],
            ['code' => 'delivery.create', 'module' => 'sales', 'description' => 'Tạo yêu cầu giao hàng Delivery Request'],

            ['code' => 'purchase.view', 'module' => 'purchase', 'description' => 'Xem danh sách và chi tiết PO'],
            ['code' => 'purchase.create', 'module' => 'purchase', 'description' => 'Tạo đơn mua hàng PO'],
            ['code' => 'purchase.approve', 'module' => 'purchase', 'description' => 'Phê duyệt đơn mua hàng PO'],

            ['code' => 'inventory.view', 'module' => 'inventory', 'description' => 'Xem tồn kho On-Hand, Reserved, Available'],
            ['code' => 'inventory.receive', 'module' => 'inventory', 'description' => 'Nhập kho thực tế Goods Receipt'],
            ['code' => 'inventory.issue', 'module' => 'inventory', 'description' => 'Xuất kho thực tế Goods Issue'],
            ['code' => 'inventory.transfer', 'module' => 'inventory', 'description' => 'Điều chuyển kho Stock Transfer'],

            ['code' => 'catalog.view', 'module' => 'catalog', 'description' => 'Xem danh mục sản phẩm và đối tác'],
            ['code' => 'catalog.create', 'module' => 'catalog', 'description' => 'Tạo mới sản phẩm hoặc đối tác'],
            ['code' => 'catalog.edit', 'module' => 'catalog', 'description' => 'Chỉnh sửa sản phẩm và giá'],

            ['code' => 'report.view', 'module' => 'analytics', 'description' => 'Xem Dashboard báo cáo quản trị'],
            ['code' => 'ai.use', 'module' => 'ai', 'description' => 'Sử dụng AI Executive Copilot'],

            ['code' => 'users.view', 'module' => 'admin', 'description' => 'Xem danh sách tài khoản'],
            ['code' => 'users.manage', 'module' => 'admin', 'description' => 'Quản trị tài khoản và phân quyền'],
        ];
        foreach ($perms as $p) {
            DB::table('permissions')->updateOrInsert(['code' => $p['code']], $p);
        }

        // Roles
        $roles = [
            ['code' => 'GENERAL_DIRECTOR', 'name' => 'Tổng Giám Đốc', 'description' => 'Toàn quyền phê duyệt, xem báo cáo toàn công ty và sử dụng AI'],
            ['code' => 'SALES_MANAGER', 'name' => 'Trưởng Phòng Kinh Doanh', 'description' => 'Quản lý quy trình bán hàng, duyệt chiết khấu'],
            ['code' => 'SALES_REP', 'name' => 'Nhân Viên Kinh Doanh', 'description' => 'Tạo báo giá, tạo SO, tạo Delivery Request'],
            ['code' => 'PURCHASE_MANAGER', 'name' => 'Trưởng Phòng Mua Hàng', 'description' => 'Quản lý tạo và duyệt PO'],
            ['code' => 'WAREHOUSE_KEEPER', 'name' => 'Thủ Kho', 'description' => 'Quản lý xuất nhập tồn Goods Receipt & Issue'],
            ['code' => 'ADMIN', 'name' => 'Quản Trị Hệ Thống', 'description' => 'Quản lý tài khoản, cấu hình và danh mục'],
        ];
        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(['code' => $r['code']], $r);
        }

        // Map Permissions
        $allPerms = DB::table('permissions')->pluck('id', 'code');
        $allRoles = DB::table('roles')->pluck('id', 'code');

        $rolePermMap = [
            'GENERAL_DIRECTOR' => $allPerms->keys()->all(),
            'SALES_MANAGER' => ['sales.view', 'sales.create', 'sales.update', 'sales.approve', 'delivery.create', 'catalog.view', 'report.view', 'ai.use', 'inventory.view'],
            'SALES_REP' => ['sales.view', 'sales.create', 'delivery.create', 'catalog.view', 'inventory.view'],
            'PURCHASE_MANAGER' => ['purchase.view', 'purchase.create', 'purchase.approve', 'catalog.view', 'report.view', 'ai.use', 'inventory.view'],
            'WAREHOUSE_KEEPER' => ['inventory.view', 'inventory.receive', 'inventory.issue', 'inventory.transfer', 'catalog.view'],
            'ADMIN' => ['users.view', 'users.manage', 'catalog.view', 'catalog.create', 'catalog.edit', 'report.view'],
        ];

        foreach ($rolePermMap as $rCode => $pList) {
            $rId = $allRoles[$rCode] ?? null;
            if (!$rId) continue;
            foreach ($pList as $pCode) {
                $pId = $allPerms[$pCode] ?? null;
                if ($pId) {
                    DB::table('role_permissions')->updateOrInsert(['role_id' => $rId, 'permission_id' => $pId], []);
                }
            }
        }

        // Users
        $defaultHash = Hash::make('password123');
        $allDepts = DB::table('departments')->pluck('id', 'code');

        $users = [
            ['email' => 'director@erp.local', 'name' => 'Nguyễn Văn Giám Đốc', 'dept' => 'BOD', 'role' => 'GENERAL_DIRECTOR'],
            ['email' => 'sales_mgr@erp.local', 'name' => 'Trần Thị Trưởng Phòng Sales', 'dept' => 'SALES', 'role' => 'SALES_MANAGER'],
            ['email' => 'sales@erp.local', 'name' => 'Lê Văn Nhân Viên Sales', 'dept' => 'SALES', 'role' => 'SALES_REP'],
            ['email' => 'purchase_mgr@erp.local', 'name' => 'Phạm Văn Mua Hàng', 'dept' => 'PURCHASE', 'role' => 'PURCHASE_MANAGER'],
            ['email' => 'warehouse@erp.local', 'name' => 'Hoàng Văn Thủ Kho', 'dept' => 'WAREHOUSE', 'role' => 'WAREHOUSE_KEEPER'],
            ['email' => 'admin@erp.local', 'name' => 'Quản Trị Viên Hệ Thống', 'dept' => null, 'role' => 'ADMIN'],
        ];

        foreach ($users as $u) {
            $deptId = $u['dept'] ? ($allDepts[$u['dept']] ?? null) : null;
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password_hash' => $defaultHash, 'department_id' => $deptId, 'is_active' => 1]
            );

            $userId = DB::table('users')->where('email', $u['email'])->value('id');
            $roleId = $allRoles[$u['role']] ?? null;
            if ($userId && $roleId) {
                DB::table('user_roles')->updateOrInsert(['user_id' => $userId, 'role_id' => $roleId], []);
            }
        }
    }
}