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
        Schema::create('finance_vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('voucher_number', 30)->unique();
            $table->string('voucher_type', 30)->index();
            $table->string('channel', 10)->index();
            $table->date('voucher_date')->index();
            $table->string('party_type', 30)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('party_name')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('cash_fund_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('total_amount', 18, 2);
            $table->string('currency', 10)->default('VND');
            $table->string('status', 20)->default('DRAFT')->index();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->unsignedBigInteger('posted_by_id')->nullable();
            $table->string('posted_by_name')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_vouchers');
    }
};
