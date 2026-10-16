<?php

namespace App\Auth;

/** Bên đang gọi API: người dùng (từ JWT) hoặc một service nội bộ (từ X-Internal-Service-Key). */
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
        public readonly bool $isService = false,
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

    /** Service nội bộ chỉ được đọc: không có role/permission nên mọi route cần quyền đều chặn. */
    public static function internalService(): self
    {
        return new self(0, 'internal-service', '', [], [], true);
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
