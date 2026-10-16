<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('supplier_type', 50)->default('TRADING')->after('supplier_group'); // TRADING, MANUFACTURER
            $table->index('supplier_type');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['supplier_type']);
            $table->dropColumn('supplier_type');
        });
    }
};
