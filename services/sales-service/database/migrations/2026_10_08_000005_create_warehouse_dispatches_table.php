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
        Schema::create('warehouse_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->unique();
            $table->string('invoice_number', 50)->index();
            $table->unsignedBigInteger('delivery_request_id')->nullable()->index();
            $table->string('delivery_request_number', 50)->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('order_number', 50)->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('warehouse_name', 150)->default('Kho Tổng Miền Bắc (Hà Nội)');
            
            // Tiến độ kho: SENT_TO_WAREHOUSE, PROCESSED, PICKING, PACKED, DISPATCHED, DELIVERED
            $table->string('status', 50)->default('SENT_TO_WAREHOUSE');
            
            // Nhân sự thực hiện (Lưu ID để join sang bảng users lấy tên)
            $table->unsignedBigInteger('picker_id')->nullable()->index();
            $table->string('picker_name', 150)->nullable();
            
            $table->unsignedBigInteger('packer_id')->nullable()->index();
            $table->string('packer_name', 150)->nullable();
            
            // Phương thức vận chuyển: CUSTOMER_PICKUP, WAREHOUSE_DELIVERY, THIRD_PARTY_CARRIER
            $table->string('shipping_method', 50)->default('CUSTOMER_PICKUP');
            $table->unsignedBigInteger('shipper_id')->nullable()->index(); // ID tài xế / người giao của kho
            $table->string('carrier_name', 150)->nullable(); // Hãng vận chuyển hoặc tên người giao
            $table->text('shipping_note')->nullable(); // Mã vận đơn / ghi chú gửi hàng
            
            // Ghi sổ xuất kho
            $table->dateTime('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by_id')->nullable();
            $table->string('posted_by_name', 150)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_dispatches');
    }
};
