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
        Schema::table('revenue_report_items', function (Blueprint $table) {
            $table->unsignedBigInteger('vat_invoice_adjustment_item_id')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revenue_report_items', function (Blueprint $table) {
            $table->dropColumn('vat_invoice_adjustment_item_id');
        });
    }
};
