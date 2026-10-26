<?php

namespace App\Support;

/** Kiểm tra quyền của người đăng nhập (actor do middleware jwt gắn) ngay trong controller, cùng quy tắc với middleware permission:. */
class ActorPermissions
{
    public static function has(object $actor, string ...$permissions): bool
    {
        if (in_array('SUPER_ADMIN', $actor->roles, true) || in_array('eoffice.manage', $actor->permissions, true)) {
            return true;
        }

        return array_intersect($permissions, $actor->permissions) !== [];
    }

    /** Như has() nhưng eoffice.manage không đi tắt: dùng cho dữ liệu lương. */
    public static function hasStrict(object $actor, string ...$permissions): bool
    {
        return in_array('SUPER_ADMIN', $actor->roles, true) || array_intersect($permissions, $actor->permissions) !== [];
    }
}
