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
        // 1. Thêm shipping_address vào sales_orders
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'shipping_address')) {
                $table->text('shipping_address')->nullable()->after('notes');
            }
        });

        // 2. Thêm customer_order_number và salesperson vào delivery_requests
        Schema::table('delivery_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_requests', 'customer_order_number')) {
                $table->string('customer_order_number', 50)->nullable()->after('order_number');
            }
            if (!Schema::hasColumn('delivery_requests', 'salesperson')) {
                $table->string('salesperson', 150)->nullable()->after('customer_name');
            }
        });

        // 3. Thêm ordered_quantity và delivered_quantity vào delivery_request_items
        Schema::table('delivery_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_request_items', 'ordered_quantity')) {
                $table->decimal('ordered_quantity', 12, 2)->default(0)->after('quantity');
            }
            if (!Schema::hasColumn('delivery_request_items', 'delivered_quantity')) {
                $table->decimal('delivered_quantity', 12, 2)->default(0)->after('ordered_quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sales_orders', 'shipping_address')) {
                $table->dropColumn('shipping_address');
            }
        });

        Schema::table('delivery_requests', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('delivery_requests', 'customer_order_number')) $cols[] = 'customer_order_number';
            if (Schema::hasColumn('delivery_requests', 'salesperson')) $cols[] = 'salesperson';
            if (!empty($cols)) $table->dropColumn($cols);
        });

        Schema::table('delivery_request_items', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('delivery_request_items', 'ordered_quantity')) $cols[] = 'ordered_quantity';
            if (Schema::hasColumn('delivery_request_items', 'delivered_quantity')) $cols[] = 'delivered_quantity';
            if (!empty($cols)) $table->dropColumn($cols);
        });
    }
};
