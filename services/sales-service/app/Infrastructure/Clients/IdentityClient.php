<?php

namespace App\Infrastructure\Clients;

/** Người dùng thuộc identity-service. */
class IdentityClient extends ServiceClient
{
    public function __construct()
    {
        parent::__construct('identity-service', (string) config('services.identity.url'), (int) config('services.identity.timeout', 3));
    }

    /**
     * @param  iterable<int>  $ids
     * @return array<int, array<string, mixed>> theo id
     */
    public function users(iterable $ids): array
    {
        $users = [];
        foreach (collect($ids)->filter(fn ($id) => is_numeric($id) && (int) $id > 0)->map(fn ($id) => (int) $id)->unique()->chunk(100) as $chunk) {
            foreach ($this->getData('/api/users', ['ids' => $chunk->implode(',')]) ?? [] as $user) {
                $users[(int) $user['id']] = $user;
            }
        }

        return $users;
    }

    public function name(?int $id): ?string
    {
        return $id ? ($this->users([$id])[$id]['name'] ?? null) : null;
    }

    /** Tìm theo tên hoặc email (form cũ gửi tên người phụ trách thay vì id). */
    public function findByNameOrEmail(string $value): ?array
    {
        $value = mb_strtolower(trim($value));

        return collect($this->getData('/api/users') ?? [])
            ->first(fn (array $u): bool => mb_strtolower((string) $u['name']) === $value || mb_strtolower((string) $u['email']) === $value);
    }
}
