<?php

namespace App\Http\Middleware;

use App\Auth\Actor;
use App\Auth\InvalidTokenException;
use App\Auth\JwtVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bắt buộc token người dùng hợp lệ; gắn Actor vào request attribute "actor". */
class AuthenticateJwt
{
    public function __construct(private readonly JwtVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return self::unauthenticated('AUTH_TOKEN_MISSING', 'Vui lòng đăng nhập để tiếp tục.');
        }

        try {
            $actor = Actor::fromClaims($this->verifier->verify($token));
        } catch (InvalidTokenException $e) {
            return self::unauthenticated($e->errorCode, $e->getMessage());
        }

        $request->attributes->set('actor', $actor);

        return $next($request);
    }

    public static function unauthenticated(string $code, string $message): Response
    {
        return response()->json(['success' => false, 'error' => ['code' => $code, 'message' => $message]], 401);
    }
}
