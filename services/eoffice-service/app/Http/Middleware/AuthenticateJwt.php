<?php

namespace App\Http\Middleware;

use App\Auth\InvalidTokenException;
use App\Auth\JwtVerifier;
use Closure;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(private readonly JwtVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->unauthenticated('AUTH_TOKEN_MISSING', 'Vui lòng đăng nhập để tiếp tục.');
        }

        try {
            $claims = $this->verifier->verify($token);
        } catch (InvalidTokenException $e) {
            return $this->unauthenticated($e->errorCode, $e->getMessage());
        }

        $actor = new stdClass;
        $actor->id = (int) $claims['sub'];
        $actor->name = (string) ($claims['name'] ?? $claims['email'] ?? 'Người dùng');
        $actor->department_id = isset($claims['department_id']) ? (int) $claims['department_id'] : null;
        $actor->roles = array_values((array) ($claims['roles'] ?? []));
        $actor->permissions = array_values((array) ($claims['permissions'] ?? []));
        $request->attributes->set('actor', $actor);

        return $next($request);
    }

    private function unauthenticated(string $code, string $message): Response
    {
        return response()->json(['success' => false, 'error' => ['code' => $code, 'message' => $message]], 401);
    }
}
