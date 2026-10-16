<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            'item-inquiry.view' => 'Xem yêu cầu hỏi hàng của mình',
            'item-inquiry.create' => 'Tạo và gửi yêu cầu hỏi hàng',
            'item-inquiry.process' => 'Xử lý yêu cầu và phiếu hỏi hàng nhà cung cấp',
        ];
        foreach ($permissions as $code => $description) DB::table('permissions')->updateOrInsert(['code' => $code], ['module' => 'purchase', 'description' => $description]);
        $roles = DB::table('roles')->pluck('id', 'code');
        $ids = DB::table('permissions')->whereIn('code', array_keys($permissions))->pluck('id', 'code');
        foreach (['SUPER_ADMIN' => array_keys($permissions), 'SALE' => ['item-inquiry.view', 'item-inquiry.create'], 'SALE_MANAGER' => ['item-inquiry.view', 'item-inquiry.create'], 'PURCHASE' => ['item-inquiry.view', 'item-inquiry.process'], 'PURCHASE_MANAGER' => ['item-inquiry.view', 'item-inquiry.process']] as $role => $codes) {
            foreach ($codes as $code) if (isset($roles[$role], $ids[$code])) DB::table('role_permissions')->insertOrIgnore(['role_id' => $roles[$role], 'permission_id' => $ids[$code]]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('code', ['item-inquiry.view', 'item-inquiry.create', 'item-inquiry.process'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
