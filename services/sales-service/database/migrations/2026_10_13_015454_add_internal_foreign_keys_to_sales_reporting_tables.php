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
        Schema::table('vat_invoices', function (Blueprint $table) {
            $table->foreign('sales_invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('sales_orders')->nullOnDelete();
        });

        Schema::table('vat_invoice_items', function (Blueprint $table) {
            $table->foreign('vat_invoice_id')->references('id')->on('vat_invoices')->cascadeOnDelete();
            $table->foreign('sales_invoice_item_id')->references('id')->on('invoice_items')->nullOnDelete();
            $table->foreign('order_item_id')->references('id')->on('sales_order_items')->nullOnDelete();
            $table->foreign('revenue_report_item_id')->references('id')->on('revenue_report_items')->restrictOnDelete();
        });

        Schema::table('revenue_report_items', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('invoice_item_id')->references('id')->on('invoice_items')->restrictOnDelete();
            $table->foreign('vat_invoice_id')->references('id')->on('vat_invoices')->nullOnDelete();
            $table->foreign('vat_invoice_item_id')->references('id')->on('vat_invoice_items')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('sales_orders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revenue_report_items', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['invoice_item_id']);
            $table->dropForeign(['vat_invoice_id']);
            $table->dropForeign(['vat_invoice_item_id']);
            $table->dropForeign(['order_id']);
        });

        Schema::table('vat_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['vat_invoice_id']);
            $table->dropForeign(['sales_invoice_item_id']);
            $table->dropForeign(['order_item_id']);
            $table->dropForeign(['revenue_report_item_id']);
        });

        Schema::table('vat_invoices', function (Blueprint $table) {
            $table->dropForeign(['sales_invoice_id']);
            $table->dropForeign(['order_id']);
        });
    }
};
