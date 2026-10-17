<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->longText('logo_data')->nullable();
            $table->string('logo_mime', 50)->nullable();
        });
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('logo_path', 500)->nullable();
        });
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn(['logo_data', 'logo_mime']);
        });
    }
};
