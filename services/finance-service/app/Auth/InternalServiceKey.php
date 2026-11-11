<?php

namespace App\Auth;

/** Khóa bí mật dùng chung giữa các service khi gọi nhau không kèm token người dùng (worker, consumer). */
final class InternalServiceKey
{
    public const HEADER = 'X-Internal-Service-Key';

    public function __construct(private readonly string $key) {}

    /** Chưa cấu hình khóa thì không chấp nhận lời gọi nội bộ nào. */
    public function matches(?string $candidate): bool
    {
        return $this->key !== '' && $candidate !== null && hash_equals($this->key, $candidate);
    }
}
