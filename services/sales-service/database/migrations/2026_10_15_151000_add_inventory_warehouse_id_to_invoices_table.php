<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Kho đã giữ chỗ tại Inventory Service; xuất kho bắt buộc dùng đúng kho này
            $table->unsignedBigInteger('inventory_warehouse_id')->nullable()->after('inventory_reservation_status');
        });

        // Dữ liệu cũ: kho = kho của yêu cầu giao hàng (nếu đã có)
        DB::table('invoices')->whereNull('inventory_warehouse_id')->whereNotNull('delivery_request_id')->orderBy('id')
            ->each(function ($invoice) {
                $warehouseId = DB::table('delivery_requests')->where('id', $invoice->delivery_request_id)->value('warehouse_id');
                if ($warehouseId) {
                    DB::table('invoices')->where('id', $invoice->id)->update(['inventory_warehouse_id' => $warehouseId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('inventory_warehouse_id');
        });
    }
};
