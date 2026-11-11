<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Danh sách đơn bán lọc theo trạng thái ở server, và customer_id là tham chiếu logic sang catalog:
     * cả hai cột bắt buộc có index (quy tắc database-per-service).
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('customer_id');
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['customer_id']);
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
        });
    }
};
