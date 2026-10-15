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
        Schema::create('finance_voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('finance_vouchers')->cascadeOnDelete();
            $table->foreignId('debit_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('credit_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_voucher_lines');
    }
};
