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
            $table->decimal('exchange_rate', 20, 6)->default(1)->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_vouchers', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
