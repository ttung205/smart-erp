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
        // 1. Thêm product_id vào quotation_items và loại bỏ gross_price dư thừa
        Schema::table('quotation_items', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_items', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('quotation_id');
                $table->index('product_id');
            }
        });

        // Bước đồng bộ product_id từ DB catalog (chạy một lần năm 2026-10) đã gỡ: sales không đọc DB service khác

        Schema::table('quotation_items', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_items', 'gross_price')) {
                $table->dropColumn('gross_price');
            }
        });

        // 2. Thêm quotation_item_id & product_id vào sales_order_items; drop gross_price, unit_price
        Schema::table('sales_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_order_items', 'quotation_item_id')) {
                $table->unsignedBigInteger('quotation_item_id')->nullable()->after('order_id');
                $table->index('quotation_item_id');
            }
            if (!Schema::hasColumn('sales_order_items', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('quotation_item_id');
                $table->index('product_id');
            }
        });

        // Đồng bộ product_id & quotation_item_id cho sales_order_items
        try {
            $soItems = DB::table('sales_order_items')->get();
            foreach ($soItems as $item) {
                $updates = [];

                // Tìm quotation_item_id từ sales_order -> quotation
                $order = DB::table('sales_orders')->where('id', $item->order_id)->first();
                if ($order && !empty($order->quotation_id)) {
                    $qItem = DB::table('quotation_items')
                        ->where('quotation_id', $order->quotation_id)
                        ->where(function ($q) use ($item, $updates) {
                            if (!empty($updates['product_id'])) {
                                $q->where('product_id', $updates['product_id']);
                            }
                            if (!empty($item->sku)) {
                                $q->orWhere('sku', $item->sku);
                            }
                        })
                        ->first();
                    if ($qItem) {
                        $updates['quotation_item_id'] = $qItem->id;
                    }
                }

                if (!empty($updates)) {
                    DB::table('sales_order_items')->where('id', $item->id)->update($updates);
                }
            }
        } catch (\Throwable $e) {
            // pass
        }

        Schema::table('sales_order_items', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['gross_price', 'unit_price'] as $col) {
                if (Schema::hasColumn('sales_order_items', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        // 3. Chuẩn hóa invoice_items: thêm order_item_id
        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'order_item_id')) {
                $table->unsignedBigInteger('order_item_id')->nullable()->after('invoice_id');
                $table->index('order_item_id');
            }
        });

        try {
            $invItems = DB::table('invoice_items')->get();
            foreach ($invItems as $invItem) {
                $invoice = DB::table('invoices')->where('id', $invItem->invoice_id)->first();
                if ($invoice && !empty($invoice->delivery_request_id)) {
                    $drItem = DB::table('delivery_request_items')
                        ->where('delivery_request_id', $invoice->delivery_request_id)
                        ->where('sku', $invItem->sku)
                        ->first();
                    if ($drItem && !empty($drItem->order_item_id)) {
                        DB::table('invoice_items')->where('id', $invItem->id)->update([
                            'order_item_id' => $drItem->order_item_id
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // pass
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_items', 'product_id')) {
                $table->dropColumn('product_id');
            }
            $table->decimal('gross_price', 18, 2)->default(0);
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_items', 'quotation_item_id')) {
                $table->dropColumn('quotation_item_id');
            }
            if (Schema::hasColumn('sales_order_items', 'product_id')) {
                $table->dropColumn('product_id');
            }
            $table->decimal('gross_price', 18, 2)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'order_item_id')) {
                $table->dropColumn('order_item_id');
            }
        });
    }
};
