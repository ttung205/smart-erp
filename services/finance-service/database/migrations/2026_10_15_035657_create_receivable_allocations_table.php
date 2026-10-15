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
        Schema::create('receivable_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('finance_vouchers')->cascadeOnDelete();
            $table->foreignId('receivable_id')->constrained('customer_receivables')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamp('allocated_at');
            $table->unique(['voucher_id', 'receivable_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receivable_allocations');
    }
};
