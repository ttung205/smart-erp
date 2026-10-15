<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vat_invoices', function (Blueprint $table) {
            $table->foreignId('adjustment_id')->nullable()->unique()->constrained('vat_invoice_adjustments')->restrictOnDelete();
        });
        Schema::table('vat_invoice_items', function (Blueprint $table) {
            $table->foreignId('adjustment_item_id')->nullable()->unique()->constrained('vat_invoice_adjustment_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vat_invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjustment_item_id');
        });
        Schema::table('vat_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjustment_id');
        });
    }
};
