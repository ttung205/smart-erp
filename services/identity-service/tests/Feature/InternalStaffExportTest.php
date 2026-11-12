<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalStaffExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_export_requires_internal_key(): void
    {
        $this->getJson('/api/internal/exports/staff')->assertForbidden();
    }

    public function test_staff_export_lists_users_with_role_names(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Kế toán', 'code' => 'FINANCE_ACCOUNTING', 'created_at' => now(), 'updated_at' => now()]);
        $userId = DB::table('users')->insertGetId(['name' => 'Nguyễn A', 'email' => 'a@erp.local', 'password_hash' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);

        $data = $this->withHeader('X-Internal-Service-Key', 'phpunit-internal-key')
            ->getJson('/api/internal/exports/staff')->assertOk()->json('data');

        $this->assertSame('Nguyễn A', $data['items'][0]['name']);
        $this->assertSame('Kế toán', $data['items'][0]['roles']);
        $this->assertNull($data['next_after_id']);
    }
}
