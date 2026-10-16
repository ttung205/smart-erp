<?php

namespace App\Auth;

use RuntimeException;

final class InvalidTokenException extends RuntimeException
{
    private function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function malformed(): self
    {
        return new self('AUTH_TOKEN_INVALID', 'Token không hợp lệ.');
    }

    public static function badSignature(): self
    {
        return new self('AUTH_TOKEN_INVALID', 'Chữ ký token không hợp lệ.');
    }

    public static function expired(): self
    {
        return new self('AUTH_TOKEN_EXPIRED', 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại.');
    }
}
