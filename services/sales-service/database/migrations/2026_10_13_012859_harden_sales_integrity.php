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
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });

        Schema::table('vat_invoices', function (Blueprint $table) {
            $table->unique(['invoice_series', 'invoice_number'], 'vat_invoice_series_number_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('inventory_reservation_status', 30)->default('PENDING')->index();
            $table->text('inventory_reservation_error')->nullable();
            $table->timestamp('inventory_reserved_at')->nullable();
            $table->string('inventory_issue_status', 30)->default('PENDING')->index();
            $table->text('inventory_issue_error')->nullable();
            $table->timestamp('inventory_issued_at')->nullable();
        });

        Schema::table('vat_invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('revenue_report_item_id')->nullable()->index()->after('sales_invoice_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vat_invoice_items', function (Blueprint $table) {
            $table->dropColumn('revenue_report_item_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'inventory_reservation_status',
                'inventory_reservation_error',
                'inventory_reserved_at',
                'inventory_issue_status',
                'inventory_issue_error',
                'inventory_issued_at',
            ]);
        });

        Schema::table('vat_invoices', function (Blueprint $table) {
            $table->dropUnique('vat_invoice_series_number_unique');
        });

        Schema::dropIfExists('document_sequences');
    }
};
