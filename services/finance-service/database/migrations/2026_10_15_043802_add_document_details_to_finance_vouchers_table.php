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
        Schema::table('finance_vouchers', function (Blueprint $table) {
            $table->date('accounting_date')->nullable()->after('voucher_date');
            $table->string('party_code', 50)->nullable()->after('party_id');
            $table->string('party_address')->nullable()->after('party_name');
            $table->string('contact_name')->nullable()->after('party_address');
            $table->string('reason_code', 50)->nullable()->after('contact_name');
            $table->string('employee_name')->nullable()->after('reason_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_vouchers', function (Blueprint $table) {
            $table->dropColumn([
                'accounting_date',
                'party_code',
                'party_address',
                'contact_name',
                'reason_code',
                'employee_name',
            ]);
        });
    }
};
