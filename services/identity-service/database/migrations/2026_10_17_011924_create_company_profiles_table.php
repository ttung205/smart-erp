<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->nullable();
            $table->string('short_name', 100)->nullable();
            $table->string('tax_code', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('office_address', 500)->nullable();
            $table->string('tel', 50)->nullable();
            $table->string('hotline', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('accounting_email', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_branch', 255)->nullable();
            $table->string('bank_beneficiary', 255)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->timestamps();
        });

        DB::table('permissions')->updateOrInsert(
            ['code' => 'company.manage'],
            ['module' => 'company', 'description' => 'Cập nhật thông tin và logo công ty', 'created_at' => now(), 'updated_at' => now()]
        );
        $roleId = DB::table('roles')->where('code', 'SUPER_ADMIN')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'company.manage')->value('id');
        if ($roleId && $permissionId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'company.manage')->value('id');
        if ($permissionId) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
        Schema::dropIfExists('company_profiles');
    }
};
