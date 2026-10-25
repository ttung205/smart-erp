<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hủy chứng từ bán hàng: warehouse_status = CANCELLED, ghi lại ai hủy, khi nào, vì sao. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_id')->nullable();
            $table->string('cancelled_by_name')->nullable();
            $table->text('cancel_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn([
            'cancelled_at', 'cancelled_by_id', 'cancelled_by_name', 'cancel_reason',
        ]));
    }
};
