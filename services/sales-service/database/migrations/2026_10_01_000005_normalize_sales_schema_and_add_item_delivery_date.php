<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Thêm delivery_date vào sales_order_items và loại bỏ các cột trùng với Catalog Product / Stock snapshot
        Schema::table('sales_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_order_items', 'delivery_date')) {
                $table->string('delivery_date', 30)->nullable()->after('lead_time');
            }
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['stock', 'designation', 'brand', 'description', 'com', 'unit'] as $col) {
                if (Schema::hasColumn('sales_order_items', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        // 2. Chuẩn hóa delivery_request_items: Thêm order_item_id, đồng bộ dữ liệu cũ, lược bỏ các cột dư thừa
        Schema::table('delivery_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_request_items', 'order_item_id')) {
                $table->unsignedBigInteger('order_item_id')->nullable()->after('delivery_request_id');
                $table->index('order_item_id');
            }
        });

        // Tự động map order_item_id cho các bản ghi giao hàng hiện có
        $drs = DB::table('delivery_requests')->get();
        foreach ($drs as $dr) {
            if (!empty($dr->order_id)) {
                $items = DB::table('delivery_request_items')
                    ->where('delivery_request_id', $dr->id)
                    ->get();
                foreach ($items as $it) {
                    $soItem = DB::table('sales_order_items')
                        ->where('order_id', $dr->order_id)
                        ->where('sku', $it->sku)
                        ->first();
                    if ($soItem) {
                        DB::table('delivery_request_items')
                            ->where('id', $it->id)
                            ->update(['order_item_id' => $soItem->id]);
                    }
                }
            }
        }

        Schema::table('delivery_request_items', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['product_name', 'ordered_quantity', 'delivered_quantity', 'unit_price'] as $col) {
                if (Schema::hasColumn('delivery_request_items', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        // 3. Lược bỏ các cột dư thừa trong quotations
        Schema::table('quotations', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['customer_code', 'contact_name'] as $col) {
                if (Schema::hasColumn('quotations', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        // 4. Lược bỏ các cột dư thừa trong quotation_items
        Schema::table('quotation_items', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['is_won', 'designation', 'brand', 'com', 'unit'] as $col) {
                if (Schema::hasColumn('quotation_items', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        // 5. Chuẩn hóa invoices: Thêm delivery_request_id, map dữ liệu, drop cột trùng lặp
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'delivery_request_id')) {
                $table->unsignedBigInteger('delivery_request_id')->nullable()->after('invoice_number');
                $table->index('delivery_request_id');
            }
        });

        // Đồng bộ delivery_request_id cho các hóa đơn hiện có
        $invs = DB::table('invoices')->get();
        foreach ($invs as $inv) {
            if (!empty($inv->delivery_request_number)) {
                $dr = DB::table('delivery_requests')
                    ->where('request_number', $inv->delivery_request_number)
                    ->orWhere('id', $inv->delivery_request_number)
                    ->first();
                if ($dr) {
                    DB::table('invoices')->where('id', $inv->id)->update([
                        'delivery_request_id' => $dr->id
                    ]);
                }
            }
        }

        Schema::table('invoices', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['order_number', 'customer_name', 'warehouse_name'] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
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
        Schema::table('sales_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_items', 'delivery_date')) {
                $table->dropColumn('delivery_date');
            }
            $table->string('designation', 200)->nullable();
            $table->string('brand', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('com', 100)->nullable();
            $table->decimal('stock', 12, 2)->default(0);
            $table->string('unit', 50)->default('Cái');
        });

        Schema::table('delivery_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_request_items', 'order_item_id')) {
                $table->dropColumn('order_item_id');
            }
            $table->string('product_name', 200)->nullable();
            $table->decimal('ordered_quantity', 12, 2)->default(0);
            $table->decimal('delivered_quantity', 12, 2)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->string('customer_code', 50)->nullable();
            $table->string('contact_name', 150)->nullable();
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->boolean('is_won')->default(false);
            $table->string('designation', 150)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('com', 100)->nullable();
            $table->string('unit', 50)->default('Cái');
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'delivery_request_id')) {
                $table->dropColumn('delivery_request_id');
            }
            $table->string('order_number', 50)->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->string('warehouse_name', 150)->nullable();
        });
    }
};
