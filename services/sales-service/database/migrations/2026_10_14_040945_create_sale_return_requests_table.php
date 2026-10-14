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
        Schema::create('sale_return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('customer_code', 100);
            $table->string('customer_name');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->string('status', 40)->default('PENDING_INSPECTION')->index();
            $table->unsignedBigInteger('technical_warehouse_id')->nullable();
            $table->string('technical_warehouse_name')->nullable();
            $table->unsignedBigInteger('sales_warehouse_id')->nullable();
            $table->string('sales_warehouse_name')->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name');
            $table->unsignedBigInteger('inspection_started_by_id')->nullable();
            $table->string('inspection_started_by_name')->nullable();
            $table->timestamp('inspection_started_at')->nullable();
            $table->unsignedBigInteger('inspected_by_id')->nullable();
            $table->string('inspected_by_name')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->unsignedBigInteger('technical_received_by_id')->nullable();
            $table->string('technical_received_by_name')->nullable();
            $table->timestamp('technical_received_at')->nullable();
            $table->unsignedBigInteger('main_requested_by_id')->nullable();
            $table->string('main_requested_by_name')->nullable();
            $table->timestamp('main_requested_at')->nullable();
            $table->unsignedBigInteger('main_approved_by_id')->nullable();
            $table->string('main_approved_by_name')->nullable();
            $table->timestamp('main_approved_at')->nullable();
            $table->unsignedBigInteger('main_rejected_by_id')->nullable();
            $table->string('main_rejected_by_name')->nullable();
            $table->timestamp('main_rejected_at')->nullable();
            $table->text('main_rejection_reason')->nullable();
            $table->unsignedBigInteger('posted_by_id')->nullable();
            $table->string('posted_by_name')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_requests');
    }
};
