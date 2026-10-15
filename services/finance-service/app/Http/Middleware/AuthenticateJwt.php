<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return response()->json(['success' => false, 'error' => ['code' => 'AUTH_TOKEN_MISSING']], 401);
        }
        $parts = explode('.', $matches[1]);
        if (count($parts) !== 3) {
            return response()->json(['success' => false, 'error' => ['code' => 'AUTH_TOKEN_INVALID']], 401);
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $decode = static function (string $value): ?array {
            $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
            $decoded = base64_decode(strtr($value, '-_', '+/'), true);
            $data = $decoded === false ? null : json_decode($decoded, true);

            return is_array($data) ? $data : null;
        };
        $header = $decode($encodedHeader);
        $payload = $decode($encodedPayload);
        $secret = (string) config('services.identity.jwt_secret');
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $secret, true)), '+/', '-_'), '=');
        if (($header['alg'] ?? null) !== 'HS256' || ! $payload || ! hash_equals($expected, $encodedSignature) || ($payload['iss'] ?? null) !== 'identity-service' || (int) ($payload['exp'] ?? 0) <= time()) {
            return response()->json(['success' => false, 'error' => ['code' => 'AUTH_TOKEN_INVALID']], 401);
        }
        $actor = new stdClass;
        $actor->id = (int) $payload['sub'];
        $actor->name = (string) ($payload['name'] ?? $payload['email'] ?? 'Người dùng');
        $actor->roles = array_values((array) ($payload['roles'] ?? []));
        $actor->permissions = array_values((array) ($payload['permissions'] ?? []));
        $request->attributes->set('actor', $actor);

        return $next($request);
    }
}
