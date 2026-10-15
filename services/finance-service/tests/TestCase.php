<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @return array<string, string> */
    protected function jwtHeaders(array $permissions = [], array $roles = []): array
    {
        $secret = 'finance-service-test-secret';
        config(['services.identity.jwt_secret' => $secret]);
        $encode = static fn (array $value): string => rtrim(strtr(base64_encode((string) json_encode($value)), '+/', '-_'), '=');
        $header = $encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = $encode(['iss' => 'identity-service', 'sub' => '42', 'name' => 'Test User', 'roles' => $roles, 'permissions' => $permissions, 'exp' => time() + 3600]);
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), '+/', '-_'), '=');

        return ['Authorization' => "Bearer {$header}.{$payload}.{$signature}"];
    }
}
