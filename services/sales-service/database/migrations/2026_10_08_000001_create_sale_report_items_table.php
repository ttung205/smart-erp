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
        Schema::create('sale_report_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('order_number')->nullable()->index();
            $table->unsignedBigInteger('order_item_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('sku')->nullable()->index();
            $table->string('product_name')->nullable();
            
            // Số lượng & Giao nhận
            $table->decimal('quantity', 12, 2)->default(0); // Số lượng đặt
            $table->decimal('delivered_quantity', 12, 2)->default(0); // Số lượng đã giao
            $table->decimal('remaining_quantity', 12, 2)->default(0); // Số lượng chưa giao
            
            // Giá & Thuế
            $table->decimal('selling_price', 15, 2)->default(0); // Đơn giá bán
            $table->decimal('vat', 8, 2)->default(0); // Thuế VAT (% hoặc giá trị)
            $table->decimal('vat_percent', 5, 2)->default(0); // % VAT
            $table->decimal('vat_amount', 15, 2)->default(0); // Tiền VAT
            $table->decimal('subtotal', 15, 2)->default(0); // Tiền trước thuế
            $table->decimal('total_amount', 15, 2)->default(0); // Tổng tiền sau thuế
            
            // Ngày hẹn giao & Thông tin tham chiếu (ID)
            $table->string('delivery_date')->nullable(); // Ngày hẹn giao
            $table->unsignedBigInteger('customer_id')->nullable()->index(); // ID khách hàng
            $table->unsignedBigInteger('salesperson_id')->nullable()->index(); // ID nhân viên kinh doanh
            $table->string('status', 30)->default('PENDING'); // PENDING, PARTIAL, DELIVERED, CANCELLED
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_report_items');
    }
};
