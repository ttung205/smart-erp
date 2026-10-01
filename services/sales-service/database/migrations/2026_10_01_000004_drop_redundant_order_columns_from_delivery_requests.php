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
        Schema::table('delivery_requests', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['order_number', 'customer_order_number', 'customer_name', 'salesperson'] as $col) {
                if (Schema::hasColumn('delivery_requests', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_requests', function (Blueprint $table) {
            $table->string('order_number', 50)->nullable();
            $table->string('customer_order_number', 50)->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->string('salesperson', 150)->nullable();
        });
    }
};
