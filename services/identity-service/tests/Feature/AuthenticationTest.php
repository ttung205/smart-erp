<?php

namespace Tests\Feature;

use App\Auth\JwtIssuer;
use App\Auth\JwtVerifier;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => 'password123'])
            ->assertOk()
            ->json('data.token');
    }

    private function forge(array $claims): string
    {
        $encode = static fn (array $value): string => JwtVerifier::encode((string) json_encode($value));

        return $encode(['typ' => 'JWT', 'alg' => 'HS256']).'.'.$encode($claims).'.forged';
    }

    public function test_login_issues_token_verifiable_by_other_services(): void
    {
        $claims = app(JwtVerifier::class)->verify($this->login('sale@erp.local'));

        $this->assertSame('identity-service', $claims['iss']);
        $this->assertContains('SALE', $claims['roles']);
        $this->assertGreaterThan(time(), $claims['exp']);
    }

    public function test_me_rejects_unsigned_token(): void
    {
        $adminId = DB::table('users')->where('email', 'admin@erp.local')->value('id');
        $token = $this->forge(['iss' => 'identity-service', 'sub' => (string) $adminId, 'exp' => time() + 60]);

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_rejects_expired_token(): void
    {
        $token = (new JwtIssuer('phpunit-jwt-secret', -1))->issue('1', []);

        $this->withToken($token)->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_TOKEN_EXPIRED');
    }

    public function test_me_returns_current_user(): void
    {
        $this->withToken($this->login('sale@erp.local'))->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'sale@erp.local');
    }

    public function test_read_routes_require_user_token_or_internal_key(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
        $this->withHeader('X-Internal-Service-Key', 'wrong-key')->getJson('/api/users')->assertUnauthorized();

        $this->withHeader('X-Internal-Service-Key', 'phpunit-internal-key')->getJson('/api/users')->assertOk();
        $this->flushHeaders();
        $this->withToken($this->login('sale@erp.local'))->getJson('/api/departments')->assertOk();
    }

    public function test_admin_writes_require_permission(): void
    {
        $payload = ['code' => 'HACKER', 'name' => 'Hacker'];

        $this->postJson('/api/roles', $payload)->assertUnauthorized();
        $this->withToken($this->login('sale@erp.local'))->postJson('/api/roles', $payload)->assertForbidden();
        $this->assertDatabaseMissing('roles', ['code' => 'HACKER']);
    }

    public function test_internal_service_cannot_write(): void
    {
        $this->withHeader('X-Internal-Service-Key', 'phpunit-internal-key')
            ->postJson('/api/roles', ['code' => 'HACKER', 'name' => 'Hacker'])
            ->assertUnauthorized();
    }

    public function test_super_admin_can_write(): void
    {
        $this->withToken($this->login('admin@erp.local'))
            ->postJson('/api/departments', ['code' => 'QA', 'name' => 'Kiểm thử'])
            ->assertSuccessful();
    }
}
