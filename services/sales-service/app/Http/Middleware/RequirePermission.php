<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $actor = $request->attributes->get('actor');
        if (! $actor) {
            return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        if (in_array('SUPER_ADMIN', $actor->roles, true)) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            $resource = strstr($permission, '.', true);
            if (in_array($permission, $actor->permissions, true) || ($resource && in_array("{$resource}.manage", $actor->permissions, true))) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'error' => ['code' => 'FORBIDDEN', 'message' => 'Bạn không có quyền thực hiện thao tác này.'],
        ], 403);
    }
}
