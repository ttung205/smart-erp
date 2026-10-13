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
            return $this->unauthenticated('AUTH_TOKEN_MISSING', 'Vui lòng đăng nhập để tiếp tục.');
        }

        $parts = explode('.', $matches[1]);
        if (count($parts) !== 3) {
            return $this->unauthenticated('AUTH_TOKEN_INVALID', 'Token không hợp lệ.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJson($encodedHeader);
        $payload = $this->decodeJson($encodedPayload);
        $secret = (string) config('services.identity.jwt_secret');

        if (($header['alg'] ?? null) !== 'HS256' || $payload === null || $secret === '') {
            return $this->unauthenticated('AUTH_TOKEN_INVALID', 'Token không hợp lệ.');
        }

        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $secret, true));
        if (! hash_equals($expectedSignature, $encodedSignature)) {
            return $this->unauthenticated('AUTH_TOKEN_INVALID', 'Chữ ký token không hợp lệ.');
        }

        $now = time();
        if (($payload['iss'] ?? null) !== 'identity-service' || ! isset($payload['sub']) || (int) ($payload['exp'] ?? 0) <= $now) {
            return $this->unauthenticated('AUTH_TOKEN_EXPIRED', 'Token đã hết hạn hoặc không hợp lệ.');
        }

        $actor = new stdClass;
        $actor->id = (int) $payload['sub'];
        $actor->name = (string) ($payload['name'] ?? '');
        $actor->email = (string) ($payload['email'] ?? '');
        $actor->roles = array_values(array_filter((array) ($payload['roles'] ?? []), 'is_string'));
        $actor->permissions = array_values(array_filter((array) ($payload['permissions'] ?? []), 'is_string'));
        $request->attributes->set('actor', $actor);

        return $next($request);
    }

    private function decodeJson(string $value): ?array
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);

        return is_array($data) ? $data : null;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function unauthenticated(string $code, string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], 401);
    }
}
