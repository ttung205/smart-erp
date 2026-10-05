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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'currency')) {
                $table->string('currency', 10)->default('VND')->after('invoice_date');
            }
            if (!Schema::hasColumn('invoices', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 4)->default(1.0)->after('currency');
            }
            if (!Schema::hasColumn('invoices', 'created_by')) {
                $table->string('created_by', 100)->default('admin')->after('exchange_rate');
            }
            if (!Schema::hasColumn('invoices', 'notes')) {
                $table->text('notes')->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('invoices', 'picker_name')) {
                $table->string('picker_name', 100)->nullable()->after('notes');
            }
            if (!Schema::hasColumn('invoices', 'packer_name')) {
                $table->string('packer_name', 100)->nullable()->after('picker_name');
            }
            if (!Schema::hasColumn('invoices', 'shipper_name')) {
                $table->string('shipper_name', 100)->nullable()->after('packer_name');
            }
            if (!Schema::hasColumn('invoices', 'shipping_note')) {
                $table->text('shipping_note')->nullable()->after('shipper_name');
            }
            if (!Schema::hasColumn('invoices', 'warehouse_posted_at')) {
                $table->string('warehouse_posted_at', 50)->nullable()->after('shipping_note');
            }
            if (!Schema::hasColumn('invoices', 'warehouse_posted_by')) {
                $table->string('warehouse_posted_by', 100)->nullable()->after('warehouse_posted_at');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'delivery_request_item_id')) {
                $table->unsignedBigInteger('delivery_request_item_id')->nullable()->after('order_item_id');
                $table->index('delivery_request_item_id');
            }
            if (!Schema::hasColumn('invoice_items', 'description')) {
                $table->text('description')->nullable()->after('product_name');
            }
            if (!Schema::hasColumn('invoice_items', 'requested_quantity')) {
                $table->decimal('requested_quantity', 12, 2)->default(0)->after('description');
            }
            if (!Schema::hasColumn('invoice_items', 'delivered_quantity')) {
                $table->decimal('delivered_quantity', 12, 2)->default(0)->after('quantity');
            }
        });

        Schema::table('delivery_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_request_items', 'delivered_quantity')) {
                $table->decimal('delivered_quantity', 12, 2)->default(0)->after('quantity');
            }
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_order_items', 'delivered_quantity')) {
                $table->decimal('delivered_quantity', 12, 2)->default(0)->after('quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_items', 'delivered_quantity')) {
                $table->dropColumn('delivered_quantity');
            }
        });

        Schema::table('delivery_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_request_items', 'delivered_quantity')) {
                $table->dropColumn('delivered_quantity');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $cols = [];
            foreach (['delivery_request_item_id', 'description', 'requested_quantity', 'delivered_quantity'] as $col) {
                if (Schema::hasColumn('invoice_items', $col)) {
                    $cols[] = $col;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            $cols = [];
            foreach (['currency', 'exchange_rate', 'created_by', 'notes', 'picker_name', 'packer_name', 'shipper_name', 'shipping_note', 'warehouse_posted_at', 'warehouse_posted_by'] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $cols[] = $col;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
