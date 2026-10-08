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
        Schema::create('revenue_report_items', function (Blueprint $table) {
            $table->id();
            
            // Tham chiếu chứng từ xuất kho
            $table->unsignedBigInteger('invoice_id')->index();
            $table->string('invoice_number', 50)->nullable()->index();
            $table->unsignedBigInteger('invoice_item_id')->index();
            
            // Tham chiếu hóa đơn VAT (nếu đã xuất hóa đơn)
            $table->unsignedBigInteger('vat_invoice_id')->nullable()->index();
            $table->string('vat_invoice_number', 50)->nullable()->index();
            $table->string('vat_invoice_series', 30)->nullable();
            $table->unsignedBigInteger('vat_invoice_item_id')->nullable()->index();
            
            // Tham chiếu đơn hàng & sản phẩm
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('order_number', 50)->nullable();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('sku', 100)->nullable()->index();
            $table->string('product_name')->nullable();
            
            // Tài khoản kế toán & Trạng thái hóa đơn
            $table->string('account_code', 10)->default('1312')->index(); // '1312': Xuất kho chưa HĐ, '1311': Đã xuất HĐ
            $table->boolean('is_vat_invoiced')->default(false)->index(); // 0: Chưa xuất HĐ, 1: Đã xuất HĐ
            
            // Số lượng
            $table->decimal('quantity', 12, 2)->default(0); // Số lượng xuất kho
            $table->decimal('invoiced_quantity', 12, 2)->default(0); // Số lượng đã xuất hóa đơn VAT
            $table->decimal('remaining_invoice_quantity', 12, 2)->default(0); // Số lượng còn lại chưa xuất HĐ
            
            // Tiền tệ, Giá bán & Thuế VAT
            $table->string('currency', 10)->default('VND');
            $table->decimal('exchange_rate', 12, 4)->default(1.0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(10);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            
            // Phân loại khách hàng, nhân viên kinh doanh
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('salesperson_id')->nullable()->index();
            
            // Ngày ghi sổ xuất kho & Ngày hóa đơn VAT
            $table->string('issue_date', 30)->nullable()->index(); // Ngày kế toán ghi sổ xuất kho
            $table->string('vat_invoice_date', 30)->nullable()->index(); // Ngày xuất hóa đơn VAT
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_report_items');
    }
};
