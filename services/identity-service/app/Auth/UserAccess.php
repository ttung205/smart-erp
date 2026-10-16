<?php

namespace App\Auth;

use Illuminate\Support\Facades\DB;

/** Đọc vai trò và quyền hiện tại của một user từ RBAC (user_roles → role_permissions). */
class UserAccess
{
    /** @return list<string> */
    public function roles(int $userId): array
    {
        return DB::table('roles')
            ->join('user_roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $userId)
            ->pluck('code')
            ->all();
    }

    /** @return list<string> */
    public function permissions(int $userId): array
    {
        return DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('user_roles', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $userId)
            ->distinct()
            ->pluck('code')
            ->all();
    }
}
