<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Categories (Danh mục sản phẩm)
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Brands (Thương hiệu sản phẩm)
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        // 3. Products (Sản phẩm chuẩn hóa: sku, designation, brand, category, item description, COM, unit, gross price, spec)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('designation', 150)->nullable();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->text('description')->nullable(); // item description
            $table->string('com', 100)->nullable()->default('Việt Nam'); // Country of Origin
            $table->string('unit', 50)->default('Cái'); // Đơn vị tính lưu chuỗi trực tiếp
            $table->decimal('gross_price', 15, 2)->default(0); // Giá niêm yết
            $table->text('spec')->nullable(); // Thông số kỹ thuật
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->integer('min_stock')->default(0);
            $table->string('status', 30)->default('ACTIVE');
            $table->timestamps();

            $table->index('sku');
            $table->index('designation');
            $table->index('brand_id');
            $table->index('category_id');
        });

        // 4. Partners (Khách hàng & Nhà cung cấp)
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->string('type', 30); // CUSTOMER, SUPPLIER, BOTH
            $table->string('tax_code', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->timestamps();
            $table->index('code');
            $table->index('type');
        });

        // 5. Warehouses (Kho bãi)
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('address', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 6. Outbox Events (Event-driven Architecture)
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 100)->unique();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 100);
            $table->string('aggregate_id', 100);
            $table->string('routing_key', 150);
            $table->longText('payload');
            $table->string('status', 30)->default('PENDING');
            $table->integer('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};