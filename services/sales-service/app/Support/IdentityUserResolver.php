<?php

namespace App\Support;

use App\Models\User;
use Throwable;

class IdentityUserResolver
{
    /** @var array<int, string|null> */
    private static array $names = [];

    public static function name(?int $userId, ?string $fallback = null): string
    {
        if (! $userId) {
            return (string) $fallback;
        }

        if (! array_key_exists($userId, self::$names)) {
            try {
                self::$names[$userId] = User::query()->whereKey($userId)->value('name');
            } catch (Throwable) {
                self::$names[$userId] = null;
            }
        }

        return self::$names[$userId] ?: (string) $fallback;
    }
}
