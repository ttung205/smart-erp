<?php

namespace App\Http\Middleware;

use App\Auth\Actor;
use App\Auth\InternalServiceKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cho route đọc được gọi cả từ frontend (JWT) lẫn từ service khác (X-Internal-Service-Key),
 * ví dụ notification-service tìm người nhận, eoffice-service tìm người duyệt.
 */
class AuthenticateUserOrService
{
    public function __construct(private readonly InternalServiceKey $serviceKey, private readonly AuthenticateJwt $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->serviceKey->matches($request->header(InternalServiceKey::HEADER))) {
            $request->attributes->set('actor', Actor::internalService());

            return $next($request);
        }

        return $this->jwt->handle($request, $next);
    }
}
