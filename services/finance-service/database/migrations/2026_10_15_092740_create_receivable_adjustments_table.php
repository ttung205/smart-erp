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
        Schema::create('receivable_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_adjustment_id')->unique();
            $table->foreignId('receivable_id')->constrained('customer_receivables')->restrictOnDelete();
            $table->decimal('total_delta', 18, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receivable_adjustments');
    }
};
