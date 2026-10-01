<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Product;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Đảm bảo quotation_items luôn lưu gross_price (Snapshot giá niêm yết thời điểm báo giá)
        Schema::table('quotation_items', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_items', 'gross_price')) {
                $table->decimal('gross_price', 18, 2)->default(0)->after('quantity');
            }
        });

        // 2. Đảm bảo sales_order_items luôn lưu gross_price (Snapshot giá niêm yết thời điểm tạo đơn)
        Schema::table('sales_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_order_items', 'gross_price')) {
                $table->decimal('gross_price', 18, 2)->default(0)->after('quantity');
            }
        });

        // Cập nhật giá trị gross_price từ Product catalog cho các bản ghi hiện tại
        try {
            $qItems = DB::table('quotation_items')->get();
            foreach ($qItems as $it) {
                if ($it->gross_price <= 0) {
                    $prod = null;
                    if (!empty($it->product_id)) {
                        $prod = Product::find($it->product_id);
                    } elseif (!empty($it->sku)) {
                        $prod = Product::where('sku', $it->sku)->first();
                    }
                    if ($prod && $prod->gross_price > 0) {
                        DB::table('quotation_items')->where('id', $it->id)->update([
                            'gross_price' => $prod->gross_price
                        ]);
                    }
                }
            }

            $soItems = DB::table('sales_order_items')->get();
            foreach ($soItems as $it) {
                if ($it->gross_price <= 0) {
                    $prod = null;
                    if (!empty($it->product_id)) {
                        $prod = Product::find($it->product_id);
                    } elseif (!empty($it->sku)) {
                        $prod = Product::where('sku', $it->sku)->first();
                    }
                    if ($prod && $prod->gross_price > 0) {
                        DB::table('sales_order_items')->where('id', $it->id)->update([
                            'gross_price' => $prod->gross_price
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
        // Không drop để tránh mất snapshot
    }
};
