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

        // Bước điền gross_price từ DB catalog (chạy một lần năm 2026-10) đã gỡ: sales không đọc DB service khác.
        // Dòng còn thiếu giá được điền bởi: php artisan sales:backfill-reference-snapshots
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Không drop để tránh mất snapshot
    }
};
