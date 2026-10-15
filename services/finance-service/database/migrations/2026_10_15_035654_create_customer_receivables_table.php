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
        Schema::create('customer_receivables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_vat_invoice_id')->nullable();
            $table->unsignedBigInteger('source_sales_invoice_id')->nullable();
            $table->string('source_type', 30)->default('VAT_INVOICE');
            $table->string('source_key', 100)->unique();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('customer_name');
            $table->string('customer_tax_code', 50)->nullable();
            $table->string('invoice_number', 50)->index();
            $table->string('invoice_series', 30)->nullable();
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('currency', 10)->default('VND');
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('original_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2);
            $table->string('status', 30)->default('OPEN')->index();
            $table->unique(['source_type', 'source_vat_invoice_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_receivables');
    }
};
