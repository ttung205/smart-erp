<?php

namespace App\Auth;

use InvalidArgumentException;

/** Ký JWT HS256 cho người dùng đã đăng nhập; các service khác kiểm tra bằng JwtVerifier cùng secret. */
final class JwtIssuer
{
    public function __construct(private readonly string $secret, private readonly int $ttlSeconds)
    {
        if ($secret === '') {
            throw new InvalidArgumentException('JWT_SECRET chưa được cấu hình.');
        }
    }

    public function ttl(): int
    {
        return $this->ttlSeconds;
    }

    /** @param  array<string, mixed>  $claims  thông tin người dùng; iss/iat/exp do issuer tự đặt */
    public function issue(string $subject, array $claims): string
    {
        $now = time();
        $header = JwtVerifier::encode((string) json_encode(['typ' => 'JWT', 'alg' => JwtVerifier::ALGORITHM]));
        $payload = JwtVerifier::encode((string) json_encode([
            'iss' => JwtVerifier::ISSUER,
            'sub' => $subject,
            ...$claims,
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ], JSON_UNESCAPED_UNICODE));

        return "{$header}.{$payload}.".JwtVerifier::sign("{$header}.{$payload}", $this->secret);
    }
}
