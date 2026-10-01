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
        // 1. Thêm order_id làm khóa ngoại chuẩn liên kết sang sales_orders
        Schema::table('delivery_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_requests', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('id');
                $table->index('order_id');
            }
        });

        // 2. Tự động đồng bộ order_id cho các bản ghi giao hàng hiện có
        $drs = DB::table('delivery_requests')->get();
        foreach ($drs as $dr) {
            if (!empty($dr->order_number)) {
                $order = DB::table('sales_orders')
                    ->where('order_number', $dr->order_number)
                    ->orWhere('id', $dr->order_number)
                    ->first();
                if ($order) {
                    DB::table('delivery_requests')->where('id', $dr->id)->update(['order_id' => $order->id]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_requests', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_requests', 'order_id')) {
                $table->dropColumn('order_id');
            }
        });
    }
};
