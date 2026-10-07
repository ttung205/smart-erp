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
        if (Schema::hasTable('delivery_request_items') && !Schema::hasColumn('delivery_request_items', 'product_id')) {
            Schema::table('delivery_request_items', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->after('order_item_id');
                $table->index('product_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('delivery_request_items') && Schema::hasColumn('delivery_request_items', 'product_id')) {
            Schema::table('delivery_request_items', function (Blueprint $table) {
                $table->dropColumn('product_id');
            });
        }
    }
};
