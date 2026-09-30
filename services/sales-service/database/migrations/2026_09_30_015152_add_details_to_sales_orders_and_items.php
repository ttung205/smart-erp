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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('customer_order_number', 50)->nullable()->after('order_number');
            $table->string('customer_code', 50)->nullable()->after('customer_id');
            $table->unsignedBigInteger('contact_id')->nullable()->after('customer_name');
            $table->string('contact_name', 150)->nullable()->after('contact_id');
            $table->string('currency', 10)->default('VND')->after('order_date');
            $table->decimal('exchange_rate', 18, 4)->default(1.0)->after('currency');
            $table->string('created_by', 100)->nullable()->after('exchange_rate');
            $table->string('assigned_to', 100)->nullable()->after('created_by');
            $table->decimal('subtotal', 18, 2)->default(0)->after('assigned_to');
            $table->decimal('vat_total', 18, 2)->default(0)->after('subtotal');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->string('designation', 200)->nullable()->after('product_name');
            $table->string('brand', 100)->nullable()->after('designation');
            $table->text('description')->nullable()->after('brand');
            $table->string('com', 100)->nullable()->after('description');
            $table->decimal('stock', 12, 2)->default(0)->after('com');
            $table->string('unit', 50)->default('Cái')->after('stock');
            $table->decimal('gross_price', 18, 2)->default(0)->after('unit');
            $table->decimal('discount_percent', 6, 2)->default(0)->after('gross_price');
            $table->decimal('selling_price', 18, 2)->default(0)->after('discount_percent');
            $table->decimal('vat_percent', 6, 2)->default(10)->after('selling_price');
            $table->string('lead_time', 100)->nullable()->after('vat_percent');
            $table->string('status', 20)->default('WIN')->after('subtotal');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->string('status', 20)->default('PENDING')->after('won_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_order_number',
                'customer_code',
                'contact_id',
                'contact_name',
                'currency',
                'exchange_rate',
                'created_by',
                'assigned_to',
                'subtotal',
                'vat_total',
            ]);
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'designation',
                'brand',
                'description',
                'com',
                'stock',
                'unit',
                'gross_price',
                'discount_percent',
                'selling_price',
                'vat_percent',
                'lead_time',
                'status',
            ]);
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['status']);
        });
    }
};
