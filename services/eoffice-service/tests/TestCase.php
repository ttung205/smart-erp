<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @return array<string, string> */
    protected function jwtHeaders(int $userId = 42, ?int $departmentId = 2, array $permissions = [], array $roles = [], string $name = 'Test User'): array
    {
        $secret = 'eoffice-service-test-secret';
        config(['services.identity.jwt_secret' => $secret]);
        $encode = static fn (array $value): string => rtrim(strtr(base64_encode((string) json_encode($value)), '+/', '-_'), '=');
        $header = $encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = $encode([
            'iss' => 'identity-service', 'sub' => (string) $userId, 'name' => $name, 'department_id' => $departmentId,
            'roles' => $roles, 'permissions' => $permissions, 'exp' => time() + 3600,
        ]);
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), '+/', '-_'), '=');

        return ['Authorization' => "Bearer {$header}.{$payload}.{$signature}"];
    }
}
