<?php

namespace Tests;

use App\Auth\JwtVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Header Authorization với token do "identity-service" ký bằng JWT_SECRET của phpunit.xml.
     *
     * @return array<string, string>
     */
    protected function jwtHeaders(array $permissions = [], array $roles = []): array
    {
        $encode = static fn (array $value): string => JwtVerifier::encode((string) json_encode($value));
        $header = $encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = $encode([
            'iss' => 'identity-service',
            'sub' => '42',
            'name' => 'Test User',
            'roles' => $roles,
            'permissions' => $permissions,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        return ['Authorization' => "Bearer {$header}.{$payload}.".JwtVerifier::sign("{$header}.{$payload}", (string) config('services.identity.jwt_secret'))];
    }
}
