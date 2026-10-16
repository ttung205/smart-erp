<?php

namespace App\Auth;

/** Người dùng đang gọi API, dựng từ claims của JWT đã xác thực. */
final class Actor
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly array $roles,
        public readonly array $permissions,
    ) {}

    /** @param  array<string, mixed>  $claims */
    public static function fromClaims(array $claims): self
    {
        return new self(
            (int) $claims['sub'],
            (string) ($claims['name'] ?? ''),
            (string) ($claims['email'] ?? ''),
            self::strings($claims['roles'] ?? []),
            self::strings($claims['permissions'] ?? []),
        );
    }

    public function hasAnyRole(string ...$roles): bool
    {
        return array_intersect($roles, $this->roles) !== [];
    }

    /** `<module>.manage` bao hàm mọi quyền cùng module; SUPER_ADMIN có mọi quyền. */
    public function can(string $permission): bool
    {
        if ($this->hasAnyRole('SUPER_ADMIN') || in_array($permission, $this->permissions, true)) {
            return true;
        }
        $module = strstr($permission, '.', true);

        return $module !== false && in_array("{$module}.manage", $this->permissions, true);
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        return array_values(array_filter((array) $values, 'is_string'));
    }
}
