<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vat_invoice_adjustment_items', function (Blueprint $table) {
            $table->decimal('vat_percent_delta', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('vat_invoice_adjustment_items', function (Blueprint $table) {
            $table->dropColumn('vat_percent_delta');
        });
    }
};
