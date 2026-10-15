<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vat_invoice_adjustments', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by_id')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->timestamp('approved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vat_invoice_adjustments', function (Blueprint $table) {
            $table->dropColumn(['approved_by_id', 'approved_by_name', 'approved_at']);
        });
    }
};
