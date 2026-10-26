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

        // 2. Permissions (Toàn bộ quyền hệ thống theo route và chức năng)
        $perms = [
            // Phân hệ Tổ chức (Organization / Identity)
            ['code' => 'department.view', 'module' => 'organization', 'description' => 'Xem danh sách và cơ cấu phòng ban (Route: /department)'],
            ['code' => 'department.manage', 'module' => 'organization', 'description' => 'Thêm, sửa, xóa phòng ban'],
            ['code' => 'employee.view', 'module' => 'organization', 'description' => 'Xem danh sách nhân viên (Route: /employee)'],
            ['code' => 'employee.manage', 'module' => 'organization', 'description' => 'Thêm, sửa, khóa nhân viên và gán vai trò'],
            ['code' => 'role.view', 'module' => 'organization', 'description' => 'Xem danh sách vai trò (Route: /role)'],
            ['code' => 'role.manage', 'module' => 'organization', 'description' => 'Thêm, sửa, xóa vai trò và chia quyền'],
            ['code' => 'permission.view', 'module' => 'organization', 'description' => 'Xem danh mục quyền hạn (Route: /permission)'],
            ['code' => 'permission.manage', 'module' => 'organization', 'description' => 'Thêm, sửa, xóa định nghĩa quyền hạn'],

            // Phân hệ Sản phẩm (Catalog)
            ['code' => 'catalog.view', 'module' => 'catalog', 'description' => 'Xem danh mục sản phẩm (Route: /catalog)'],
            ['code' => 'catalog.manage', 'module' => 'catalog', 'description' => 'Thêm, sửa, xóa sản phẩm và bảng giá'],
            ['code' => 'catalog.create', 'module' => 'catalog', 'description' => 'Tạo mới sản phẩm'],
            ['code' => 'catalog.edit', 'module' => 'catalog', 'description' => 'Chỉnh sửa sản phẩm'],
            ['code' => 'customer.view', 'module' => 'catalog', 'description' => 'Xem danh sách khách hàng'],
            ['code' => 'supplier.view', 'module' => 'catalog', 'description' => 'Xem danh sách nhà cung cấp'],

            // Phân hệ Kho hàng (Inventory)
            ['code' => 'inventory.view', 'module' => 'inventory', 'description' => 'Xem tồn kho On-Hand, Reserved (Route: /inventory)'],
            ['code' => 'inventory.manage', 'module' => 'inventory', 'description' => 'Quản trị nghiệp vụ nhập, xuất, điều chuyển kho'],
            ['code' => 'inventory.receive', 'module' => 'inventory', 'description' => 'Nhập kho thực tế Goods Receipt'],
            ['code' => 'inventory.issue', 'module' => 'inventory', 'description' => 'Xuất kho thực tế Goods Issue'],
            ['code' => 'inventory.transfer', 'module' => 'inventory', 'description' => 'Điều chuyển kho Stock Transfer'],

            // Phân hệ Báo giá (Quotation)
            ['code' => 'quotation.view', 'module' => 'quotation', 'description' => 'Xem danh sách và chi tiết báo giá (Route: /quotation)'],
            ['code' => 'quotation.create', 'module' => 'quotation', 'description' => 'Tạo mới báo giá'],
            ['code' => 'quotation.update', 'module' => 'quotation', 'description' => 'Cập nhật thông tin báo giá'],
            ['code' => 'quotation.cancel', 'module' => 'quotation', 'description' => 'Hủy báo giá'],
            ['code' => 'quotation.approve', 'module' => 'quotation', 'description' => 'Phê duyệt báo giá'],
            ['code' => 'quotation.manage', 'module' => 'quotation', 'description' => 'Quản trị nghiệp vụ báo giá'],

            // Phân hệ Đơn bán hàng (Order)
            ['code' => 'order.view', 'module' => 'order', 'description' => 'Xem danh sách và chi tiết đơn bán hàng SO (Route: /order)'],
            ['code' => 'order.create', 'module' => 'order', 'description' => 'Tạo mới đơn bán hàng SO'],
            ['code' => 'order.update', 'module' => 'order', 'description' => 'Cập nhật thông tin đơn bán hàng SO'],
            ['code' => 'order.cancel', 'module' => 'order', 'description' => 'Hủy đơn đặt hàng SO'],
            ['code' => 'order.approve', 'module' => 'order', 'description' => 'Phê duyệt đơn bán hàng SO'],
            ['code' => 'order.manage', 'module' => 'order', 'description' => 'Quản trị nghiệp vụ đơn bán hàng SO'],

            // Phân hệ Giao hàng & Hóa đơn (Delivery & Invoice)
            ['code' => 'delivery.view', 'module' => 'delivery', 'description' => 'Xem danh sách yêu cầu giao hàng (Route: /delivery)'],
            ['code' => 'delivery.create', 'module' => 'delivery', 'description' => 'Tạo yêu cầu giao hàng Delivery Request'],
            ['code' => 'delivery.manage', 'module' => 'delivery', 'description' => 'Quản trị giao hàng'],
            ['code' => 'invoice.view', 'module' => 'invoice', 'description' => 'Xem danh sách chứng từ và hóa đơn (Route: /invoice)'],
            ['code' => 'invoice.create', 'module' => 'invoice', 'description' => 'Tạo chứng từ xuất hóa đơn'],
            ['code' => 'sale-invoice.create', 'module' => 'invoice', 'description' => 'Lập chứng từ bán hàng từ yêu cầu giao hàng'],
            ['code' => 'invoice.manage', 'module' => 'invoice', 'description' => 'Quản trị chứng từ và hóa đơn'],

            // Phân hệ Hàng bán trả lại
            ['code' => 'sale_return.view', 'module' => 'sale_return', 'description' => 'Xem yêu cầu nhập trả và phiếu trả hàng bán'],
            ['code' => 'sale_return.create', 'module' => 'sale_return', 'description' => 'Tạo yêu cầu nhập hàng bán trả lại'],
            ['code' => 'sale_return.technical', 'module' => 'sale_return', 'description' => 'Kiểm tra chất lượng và nhập kho kỹ thuật'],
            ['code' => 'sale_return.approve', 'module' => 'sale_return', 'description' => 'Duyệt yêu cầu nhập kho chính'],
            ['code' => 'sale_return.post', 'module' => 'sale_return', 'description' => 'Ghi sổ và lập phiếu trả hàng bán'],
            ['code' => 'sale_return.manage', 'module' => 'sale_return', 'description' => 'Toàn quyền nghiệp vụ hàng bán trả lại'],

            // Hạ tầng hệ thống & Outbox
            ['code' => 'architecture.manage', 'module' => 'system', 'description' => 'Quản trị hạ tầng hệ thống và đồng bộ Outbox'],

            // Khác
            ['code' => 'purchase.view', 'module' => 'purchase', 'description' => 'Xem danh sách đơn mua hàng PO'],
            ['code' => 'purchase.create', 'module' => 'purchase', 'description' => 'Tạo mới đơn mua hàng PO'],
            ['code' => 'purchase.approve', 'module' => 'purchase', 'description' => 'Phê duyệt đơn mua hàng PO'],
            ['code' => 'item-inquiry.view', 'module' => 'purchase', 'description' => 'Xem yêu cầu hỏi hàng của mình'],
            ['code' => 'item-inquiry.create', 'module' => 'purchase', 'description' => 'Tạo và gửi yêu cầu hỏi hàng'],
            ['code' => 'item-inquiry.process', 'module' => 'purchase', 'description' => 'Xử lý yêu cầu và phiếu hỏi hàng nhà cung cấp'],
            ['code' => 'finance.view', 'module' => 'finance', 'description' => 'Xem báo cáo tài chính và công nợ'],
            ['code' => 'finance.create', 'module' => 'finance', 'description' => 'Lập chứng từ và yêu cầu điều chỉnh hóa đơn'],
            ['code' => 'finance.approve', 'module' => 'finance', 'description' => 'Duyệt thanh toán và xuất hóa đơn'],
            ['code' => 'eoffice.manage', 'module' => 'eoffice', 'description' => 'Quản trị văn phòng số: loại nghỉ, ngày lễ, quỹ phép nhân viên'],
            ['code' => 'ai.use', 'module' => 'ai', 'description' => 'Sử dụng trợ lý AI Copilot phân tích dữ liệu'],
            ['code' => 'users.view', 'module' => 'admin', 'description' => 'Xem danh sách tài khoản'],
            ['code' => 'users.manage', 'module' => 'admin', 'description' => 'Quản trị tài khoản và phân quyền'],
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

        // Xóa sạch các role cũ không nằm trong 11 role chuẩn hóa
        $validRoleCodes = array_column($roles, 'code');
        $oldRoleIds = DB::table('roles')->whereNotIn('code', $validRoleCodes)->pluck('id');
        DB::table('role_permissions')->whereIn('role_id', $oldRoleIds)->delete();
        DB::table('user_roles')->whereIn('role_id', $oldRoleIds)->delete();
        DB::table('roles')->whereNotIn('code', $validRoleCodes)->delete();

        // Xóa bảng phân quyền cũ để chuẩn hóa sạch sẽ
        DB::table('role_permissions')->truncate();

        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(['code' => $r['code']], $r);
        }

        // 4. Map Permissions: SUPER_ADMIN có full quyền, SALE và SALE_MANAGER có quyền phân hệ bán hàng
        $allPerms = DB::table('permissions')->pluck('id', 'code');
        $superAdminRoleId = DB::table('roles')->where('code', 'SUPER_ADMIN')->value('id');

        if ($superAdminRoleId) {
            foreach ($allPerms as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $superAdminRoleId,
                    'permission_id' => $permId,
                ]);
            }
        }

        // Phân quyền cho SALE
        $saleRoleId = DB::table('roles')->where('code', 'SALE')->value('id');
        if ($saleRoleId) {
            $salePermCodes = [
                'quotation.view', 'quotation.create', 'quotation.update', 'quotation.cancel',
                'order.view', 'order.create', 'order.update',
                'delivery.view', 'delivery.create',
                'sale_return.view', 'sale_return.create', 'catalog.view', 'customer.view', 'inventory.view', 'item-inquiry.view', 'item-inquiry.create',
            ];
            foreach ($salePermCodes as $code) {
                if (isset($allPerms[$code])) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $saleRoleId,
                        'permission_id' => $allPerms[$code],
                    ]);
                }
            }
        }

        // Phân quyền cho SALE_MANAGER
        $saleManagerRoleId = DB::table('roles')->where('code', 'SALE_MANAGER')->value('id');
        if ($saleManagerRoleId) {
            $smPermCodes = [
                'quotation.view', 'quotation.create', 'quotation.update', 'quotation.cancel', 'quotation.approve', 'quotation.manage',
                'order.view', 'order.create', 'order.update', 'order.cancel', 'order.approve', 'order.manage',
                'delivery.view', 'delivery.create', 'delivery.manage',
                'sale_return.view', 'sale_return.create', 'sale_return.approve',
                'catalog.view', 'customer.view', 'inventory.view', 'item-inquiry.view', 'item-inquiry.create',
            ];
            foreach ($smPermCodes as $code) {
                if (isset($allPerms[$code])) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $saleManagerRoleId,
                        'permission_id' => $allPerms[$code],
                    ]);
                }
            }
        }

        $rolePermissionMap = [
            'PURCHASE' => ['purchase.view', 'purchase.create', 'catalog.view', 'supplier.view', 'inventory.view', 'item-inquiry.view', 'item-inquiry.process'],
            'PURCHASE_MANAGER' => ['purchase.view', 'purchase.create', 'purchase.approve', 'catalog.view', 'supplier.view', 'inventory.view', 'item-inquiry.view', 'item-inquiry.process'],
            'TECHNICAL' => ['sale_return.view', 'sale_return.technical', 'catalog.view', 'customer.view', 'supplier.view', 'inventory.view'],
            'TECHNICAL_MANAGER' => ['sale_return.view', 'sale_return.technical', 'sale_return.approve', 'catalog.view', 'customer.view', 'supplier.view', 'inventory.view'],
            'LOGISTIC' => ['sale_return.view', 'sale_return.approve', 'catalog.view', 'inventory.view', 'inventory.transfer', 'invoice.view', 'invoice.manage'],
            'LOGISTIC_MANAGER' => ['sale_return.view', 'sale_return.approve', 'sale_return.post', 'catalog.view', 'inventory.view', 'inventory.manage', 'invoice.view', 'invoice.manage'],
            'FINANCE_ACCOUNTING' => ['finance.view', 'finance.create', 'invoice.view', 'invoice.create', 'sale-invoice.create', 'sale_return.view', 'sale_return.post', 'delivery.view', 'catalog.view', 'customer.view', 'supplier.view', 'inventory.view', 'attendance.view'],
            'FINANCE_ACCOUNTING_MANAGER' => ['finance.view', 'finance.create', 'finance.approve', 'invoice.view', 'invoice.create', 'sale-invoice.create', 'invoice.manage', 'sale_return.view', 'sale_return.approve', 'sale_return.post', 'delivery.view', 'catalog.view', 'customer.view', 'supplier.view', 'inventory.view', 'attendance.view', 'attendance.manage', 'payroll.view', 'payroll.manage', 'payroll.pay'],
        ];
        foreach ($rolePermissionMap as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            foreach ($permissionCodes as $code) {
                if ($roleId && isset($allPerms[$code])) {
                    DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $allPerms[$code]]);
                }
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
                'role' => 'SUPER_ADMIN',
            ],
            [
                'email' => 'sale@erp.local',
                'name' => 'Chuyên Viên Kinh Doanh (Sale)',
                'dept' => 'SALES',
                'role' => 'SALE',
            ],
            [
                'email' => 'sale.manager@erp.local',
                'name' => 'Trưởng Phòng Kinh Doanh (Sale Manager)',
                'dept' => 'SALES',
                'role' => 'SALE_MANAGER',
            ],
            [
                'email' => 'purchase@erp.local',
                'name' => 'Chuyên Viên Mua Hàng (Purchase)',
                'dept' => 'PURCHASE',
                'role' => 'PURCHASE',
            ],
            [
                'email' => 'purchase.manager@erp.local',
                'name' => 'Trưởng Phòng Mua Hàng (Purchase Manager)',
                'dept' => 'PURCHASE',
                'role' => 'PURCHASE_MANAGER',
            ],
            [
                'email' => 'technical@erp.local',
                'name' => 'Kỹ Thuật Viên Hệ Thống (Technical)',
                'dept' => 'TECHNICAL',
                'role' => 'TECHNICAL',
            ],
            [
                'email' => 'technical.manager@erp.local',
                'name' => 'Trưởng Phòng Kỹ Thuật (Technical Manager)',
                'dept' => 'TECHNICAL',
                'role' => 'TECHNICAL_MANAGER',
            ],
            [
                'email' => 'finance@erp.local',
                'name' => 'Chuyên Viên Tài Chính Kế Toán (Finance)',
                'dept' => 'FINANCE',
                'role' => 'FINANCE_ACCOUNTING',
            ],
            [
                'email' => 'finance.manager@erp.local',
                'name' => 'Kế Toán Trưởng (Finance Manager)',
                'dept' => 'FINANCE',
                'role' => 'FINANCE_ACCOUNTING_MANAGER',
            ],
            [
                'email' => 'logistic@erp.local',
                'name' => 'Chuyên Viên Kho Vận (Logistic)',
                'dept' => 'LOGISTIC',
                'role' => 'LOGISTIC',
            ],
            [
                'email' => 'logistic.manager@erp.local',
                'name' => 'Trưởng Phòng Kho Vận (Logistic Manager)',
                'dept' => 'LOGISTIC',
                'role' => 'LOGISTIC_MANAGER',
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
                    'updated_at' => now(),
                ]
            );

            $userId = DB::table('users')->where('email', $u['email'])->value('id');
            $roleId = $allRoles[$u['role']] ?? null;
            if ($userId && $roleId) {
                DB::table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ]);
            }
        }

        // 6. Trưởng phòng (người duyệt đơn E-Office của phòng)
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

        // 7. Quản lý trực tiếp (line manager): nhân viên → trưởng phòng của mình, trưởng phòng → Super Admin
        $adminId = DB::table('users')->where('email', 'admin@erp.local')->value('id');
        $managerIds = DB::table('departments')->whereNotNull('manager_id')->pluck('manager_id', 'id');
        foreach (DB::table('users')->get(['id', 'department_id']) as $user) {
            $lineManagerId = $managerIds->contains($user->id) ? $adminId : ($managerIds[$user->department_id] ?? null);
            DB::table('users')->where('id', $user->id)->update([
                'line_manager_id' => $lineManagerId !== $user->id ? $lineManagerId : null,
            ]);
        }
    }
}
