<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot dữ liệu khách hàng / sản phẩm / người dùng vào chứng từ (architecture.md §5.1)
 * để sales-service không còn đọc thẳng DB của catalog-service và identity-service.
 * Dữ liệu cũ được điền bằng lệnh: php artisan sales:backfill-reference-snapshots
 */
return new class extends Migration
{
    private const CUSTOMER = [
        'customer_code', 'customer_tax_code', 'customer_address', 'customer_phone', 'customer_email',
        'contact_name', 'contact_phone', 'contact_email',
    ];

    private const PRODUCT = ['designation', 'brand', 'com', 'unit'];

    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            foreach (self::CUSTOMER as $column) {
                $table->string($column)->nullable();
            }
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('customer_name')->nullable();
            foreach (self::CUSTOMER as $column) {
                $table->string($column)->nullable();
            }
            $table->string('created_by_name')->nullable();
            $table->string('assigned_to_name')->nullable();
            $table->string('approved_by_name')->nullable();
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            foreach (self::PRODUCT as $column) {
                $table->string($column)->nullable();
            }
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            foreach (self::PRODUCT as $column) {
                $table->string($column)->nullable();
            }
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', fn (Blueprint $table) => $table->dropColumn([...self::PRODUCT, 'description']));
        Schema::table('quotation_items', fn (Blueprint $table) => $table->dropColumn(self::PRODUCT));
        Schema::table('sales_orders', fn (Blueprint $table) => $table->dropColumn([
            'customer_name', ...self::CUSTOMER, 'created_by_name', 'assigned_to_name', 'approved_by_name',
        ]));
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn(self::CUSTOMER));
    }
};
