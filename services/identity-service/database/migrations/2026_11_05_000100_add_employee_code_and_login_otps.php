<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Mã nhân viên dạng AC-2005: dùng để đăng nhập. UNIQUE vì là mã nghiệp vụ.
            $table->string('employee_code', 10)->nullable()->unique()->after('id');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            // Tiền tố mã nhân viên, tối đa 2 chữ cái in hoa.
            $table->string('employee_code_prefix', 2)->nullable();
        });

        Schema::create('login_otps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_otps');
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('employee_code_prefix');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_code']);
            $table->dropColumn('employee_code');
        });
    }
};
