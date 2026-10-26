<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Như {@see RequirePermission} nhưng KHÔNG cho quyền chung eoffice.manage đi tắt: dùng cho dữ liệu lương,
 * để người quản trị văn phòng số không tự động xem/duyệt/chi lương nếu chưa được cấp đúng quyền payroll.*.
 */
class RequireStrictPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $actor = $request->attributes->get('actor');
        if (! $actor) {
            return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHENTICATED']], 401);
        }
        if (in_array('SUPER_ADMIN', $actor->roles, true) || array_intersect($permissions, $actor->permissions) !== []) {
            return $next($request);
        }

        return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Bạn không có quyền truy cập dữ liệu lương.']], 403);
    }
}
