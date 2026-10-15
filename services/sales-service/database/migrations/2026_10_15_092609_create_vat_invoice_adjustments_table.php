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
        Schema::create('vat_invoice_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 50)->unique();
            $table->foreignId('original_vat_invoice_id')->constrained('vat_invoices')->restrictOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained('sale_returns')->restrictOnDelete();
            $table->string('direction', 10);
            $table->string('adjustment_type', 20);
            $table->date('adjustment_date');
            $table->text('reason');
            $table->decimal('subtotal_delta', 18, 2);
            $table->decimal('vat_delta', 18, 2);
            $table->decimal('total_delta', 18, 2);
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('finance_synced_at')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
            $table->unique(['sale_return_id', 'original_vat_invoice_id'], 'vat_adjustment_return_invoice_unique');
        });

        Schema::create('vat_invoice_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vat_invoice_adjustment_id')->constrained('vat_invoice_adjustments')->cascadeOnDelete();
            $table->foreignId('original_vat_invoice_item_id')->constrained('vat_invoice_items')->restrictOnDelete();
            $table->decimal('original_quantity', 12, 2);
            $table->decimal('original_unit_price', 18, 2);
            $table->decimal('original_vat_percent', 5, 2);
            $table->decimal('quantity_delta', 12, 2)->default(0);
            $table->decimal('unit_price_delta', 18, 2)->default(0);
            $table->decimal('subtotal_delta', 18, 2)->default(0);
            $table->decimal('vat_delta', 18, 2)->default(0);
            $table->decimal('total_delta', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_invoice_adjustment_items');
        Schema::dropIfExists('vat_invoice_adjustments');
    }
};
