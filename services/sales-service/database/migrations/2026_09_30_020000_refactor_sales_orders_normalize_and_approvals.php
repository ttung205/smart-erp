<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add quotation_id and approval fields if not already present
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'quotation_id')) {
                $table->unsignedBigInteger('quotation_id')->nullable()->after('customer_order_number');
            }
            if (!Schema::hasColumn('sales_orders', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            }
            if (!Schema::hasColumn('sales_orders', 'approved_at')) {
                $table->string('approved_at', 50)->nullable()->after('approved_by');
            }
        });

        // 2. Map existing quotation_number to quotation_id and text created_by/assigned_to to user IDs
        if (Schema::hasColumn('sales_orders', 'quotation_number')) {
            $orders = DB::table('sales_orders')->get();
            foreach ($orders as $so) {
                $update = [];
                if (!empty($so->quotation_number)) {
                    $q = DB::table('quotations')->where('quotation_number', $so->quotation_number)->first();
                    if ($q) {
                        $update['quotation_id'] = $q->id;
                    }
                }
                if (empty($so->created_by) || !is_numeric($so->created_by)) {
                    $update['created_by'] = 6;
                }
                if (empty($so->assigned_to) || !is_numeric($so->assigned_to)) {
                    $update['assigned_to'] = 6;
                }
                if (!empty($update)) {
                    DB::table('sales_orders')->where('id', $so->id)->update($update);
                }
            }
        }

        // 3. Drop redundant columns: customer_name, customer_code, contact_name, quotation_number
        Schema::table('sales_orders', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['customer_name', 'customer_code', 'contact_name', 'quotation_number'] as $col) {
                if (Schema::hasColumn('sales_orders', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('quotation_number', 50)->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->string('customer_code', 50)->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->dropColumn(['quotation_id', 'approved_by', 'approved_at']);
        });
    }
};
