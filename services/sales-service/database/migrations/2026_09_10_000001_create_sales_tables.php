<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Quotations (Báo Giá)
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number', 50)->unique(); // Quy ước: QT-[mã KH]-[ddmmyy]
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_code', 50)->nullable();
            $table->string('customer_name', 200);
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->string('quotation_date', 30);
            $table->string('currency', 10)->default('VND');
            $table->decimal('exchange_rate', 15, 4)->default(1.0);
            $table->string('created_by', 100)->default('admin');
            $table->string('assigned_to', 100)->nullable();
            $table->string('status', 30)->default('DRAFT'); // DRAFT, PENDING_APPROVAL, APPROVED, CONVERTED, REJECTED
            $table->string('approved_by', 100)->nullable();
            $table->string('approved_at', 50)->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('vat_total', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('quotation_number');
            $table->index('status');
        });

        // 2. Quotation Items (Chi tiết mặt hàng báo giá)
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('sku', 50);
            $table->string('designation', 150)->nullable();
            $table->string('brand', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('com', 100)->nullable()->default('Việt Nam');
            $table->string('unit', 50)->default('Cái');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('gross_price', 18, 2)->default(0);
            $table->decimal('discount_percent', 6, 2)->default(0);
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->decimal('vat_percent', 6, 2)->default(10);
            $table->string('lead_time', 100)->nullable()->default('Có sẵn');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->boolean('is_won')->default(false); // Đánh dấu Item Win
            $table->decimal('won_quantity', 12, 2)->default(0);
            $table->timestamps();

            $table->index('quotation_id');
            $table->index('sku');
        });

        // 3. Sales Orders (Đơn Bán Hàng)
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->string('quotation_number', 50)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name', 200);
            $table->string('order_date', 30);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('CONFIRMED'); // CONFIRMED, PROCESSING, COMPLETED, CANCELLED
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_number');
        });

        // 4. Sales Order Items
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->string('sku', 50);
            $table->string('product_name', 200);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->timestamps();
        });

        // 5. Delivery Requests (Yêu Cầu Giao Hàng)
        Schema::create('delivery_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->string('order_number', 50)->nullable();
            $table->string('customer_name', 200);
            $table->string('warehouse_name', 150)->default('Kho Tổng Miền Bắc (Hà Nội)');
            $table->text('shipping_address')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('request_date', 30);
            $table->string('expected_delivery_date', 30)->nullable();
            $table->string('status', 30)->default('PENDING'); // PENDING, APPROVED, DELIVERED, REJECTED
            $table->timestamps();

            $table->index('request_number');
        });

        // 6. Delivery Request Items
        Schema::create('delivery_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_request_id')->constrained('delivery_requests')->cascadeOnDelete();
            $table->string('sku', 50);
            $table->string('product_name', 200);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->timestamps();
        });

        // 7. Invoices (Chứng Từ Bán Hàng)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->string('delivery_request_number', 50)->nullable();
            $table->string('order_number', 50)->nullable();
            $table->string('customer_name', 200);
            $table->string('warehouse_name', 150)->nullable();
            $table->string('invoice_date', 30);
            $table->string('payment_terms', 100)->default('Công nợ 30 ngày');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('warehouse_status', 30)->default('DELIVERED'); // PENDING, DELIVERED
            $table->string('accounting_status', 30)->default('UNPOSTED'); // UNPOSTED, POSTED
            $table->string('posted_at', 50)->nullable();
            $table->string('posted_by', 100)->nullable();
            $table->timestamps();

            $table->index('invoice_number');
        });

        // 8. Invoice Items
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('sku', 50);
            $table->string('product_name', 200);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('delivery_request_items');
        Schema::dropIfExists('delivery_requests');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
