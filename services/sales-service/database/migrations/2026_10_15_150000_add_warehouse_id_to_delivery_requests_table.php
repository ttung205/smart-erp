<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_requests', function (Blueprint $table) {
            // ID kho bên inventory-service; trước đây chỉ lưu tên kho rồi đoán ID theo tên khi giữ chỗ
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('request_number');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_requests', function (Blueprint $table) {
            $table->dropColumn('warehouse_id');
        });
    }
};
