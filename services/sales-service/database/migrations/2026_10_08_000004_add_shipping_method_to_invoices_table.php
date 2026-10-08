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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'shipping_method')) {
                $table->string('shipping_method', 50)->nullable()->after('notes'); // 'CUSTOMER_PICKUP' (Khách tới lấy), 'WAREHOUSE_DELIVERY' (Kho giao), 'THIRD_PARTY_CARRIER' (Gửi vận chuyển)
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'shipping_method')) {
                $table->dropColumn('shipping_method');
            }
        });
    }
};
