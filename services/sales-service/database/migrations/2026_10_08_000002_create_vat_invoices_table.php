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
        Schema::create('vat_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->index(); // Số hóa đơn (VD: 0001234)
            $table->string('invoice_series', 30)->nullable()->index(); // Ký hiệu hóa đơn (VD: 1C26TBB)
            $table->string('invoice_date', 20)->index(); // Ngày lập hóa đơn GTGT
            
            // Tham chiếu chứng từ xuất kho & Đơn bán hàng
            $table->unsignedBigInteger('sales_invoice_id')->nullable()->index();
            $table->string('sales_invoice_number', 50)->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('order_number', 50)->nullable()->index();
            
            // Thông tin người mua / khách hàng
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_tax_code', 50)->nullable();
            $table->text('customer_address')->nullable();
            
            // Tiền tệ & Tỷ giá
            $table->string('currency', 10)->default('VND');
            $table->decimal('exchange_rate', 12, 4)->default(1.0);
            
            // Giá trị hóa đơn
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            
            $table->string('created_by')->nullable();
            $table->string('status', 30)->default('ISSUED'); // ISSUED, CANCELLED
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vat_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vat_invoice_id')->index();
            $table->unsignedBigInteger('sales_invoice_item_id')->nullable()->index();
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('sku', 100)->nullable()->index();
            $table->string('product_name')->nullable();
            
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_percent', 5, 2)->default(10);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_invoice_items');
        Schema::dropIfExists('vat_invoices');
    }
};
