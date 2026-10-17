<?php

namespace Tests\Feature;

use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
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
            ->assertOk()->json('data.token');
    }

    public function test_company_profile_is_shared_and_only_admin_can_change_it(): void
    {
        $this->getJson('/api/company')->assertUnauthorized();
        $this->withToken($this->login('sale@erp.local'))
            ->putJson('/api/company', ['name' => 'Không được sửa'])
            ->assertForbidden();

        $this->withToken($this->login('admin@erp.local'))
            ->putJson('/api/company', [
                'name' => 'Công ty Minh An',
                'tax_code' => '0101234567',
                'email' => 'info@minhan.vn',
                'bank_account_number' => '123456789',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Công ty Minh An');

        $this->withToken($this->login('sale@erp.local'))
            ->getJson('/api/company')
            ->assertOk()
            ->assertJsonPath('data.bank_account_number', '123456789');
    }

    public function test_logo_upload_and_removal(): void
    {
        $this->withToken($this->login('admin@erp.local'));
        $this->postJson('/api/company/logo', ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertOk()->assertJsonStructure(['data' => ['logo_url']]);
        $this->assertNotEmpty(DB::table('company_profiles')->where('id', 1)->value('logo_data'));

        $this->get('/api/company/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->deleteJson('/api/company/logo')->assertOk()->assertJsonPath('data.logo_url', null);
        $this->get('/api/company/logo')->assertNotFound();

    }
}
