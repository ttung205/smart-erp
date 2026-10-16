<?php

namespace App\Auth;

use InvalidArgumentException;

/**
 * Kiểm tra JWT HS256 do identity-service cấp (cùng quy tắc ở mọi service, xem docs/contracts).
 * Không phụ thuộc HTTP/framework: middleware chỉ việc lấy token rồi gọi verify().
 */
final class JwtVerifier
{
    public const ALGORITHM = 'HS256';

    public const ISSUER = 'identity-service';

    public function __construct(private readonly string $secret)
    {
        if ($secret === '') {
            throw new InvalidArgumentException('JWT_SECRET chưa được cấu hình.');
        }
    }

    /**
     * @return array<string, mixed> claims đã được xác thực chữ ký, nguồn cấp và thời hạn
     *
     * @throws InvalidTokenException
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw InvalidTokenException::malformed();
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;
        if ((self::decode($encodedHeader)['alg'] ?? null) !== self::ALGORITHM) {
            throw InvalidTokenException::malformed();
        }

        // So chữ ký trước khi tin bất kỳ claim nào trong payload
        if (! hash_equals(self::sign("{$encodedHeader}.{$encodedPayload}", $this->secret), $signature)) {
            throw InvalidTokenException::badSignature();
        }

        $claims = self::decode($encodedPayload);
        if ($claims === null || ($claims['iss'] ?? null) !== self::ISSUER || empty($claims['sub'])) {
            throw InvalidTokenException::malformed();
        }
        if ((int) ($claims['exp'] ?? 0) <= time()) {
            throw InvalidTokenException::expired();
        }

        return $claims;
    }

    public static function sign(string $data, string $secret): string
    {
        return self::encode(hash_hmac('sha256', $data, $secret, true));
    }

    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @return array<string, mixed>|null */
    private static function decode(string $value): ?array
    {
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        $data = $decoded === false ? null : json_decode($decoded, true);

        return is_array($data) ? $data : null;
    }
}
