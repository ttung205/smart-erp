<?php

namespace App\Http\Middleware;

use App\Auth\Actor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Dùng sau jwt: permission:a.manage,b.manage cho qua nếu có ít nhất một quyền. */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $actor = $request->attributes->get('actor');
        if (! $actor instanceof Actor) {
            return AuthenticateJwt::unauthenticated('UNAUTHENTICATED', 'Vui lòng đăng nhập để tiếp tục.');
        }

        foreach ($permissions as $permission) {
            if ($actor->can($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'error' => ['code' => 'FORBIDDEN', 'message' => 'Bạn không có quyền thực hiện thao tác này.'],
        ], 403);
    }
}
