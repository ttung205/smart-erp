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
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('sale_return_request_id')->unique()->constrained()->restrictOnDelete();
            $table->string('request_number');
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('customer_code', 100);
            $table->string('customer_name');
            $table->date('return_date');
            $table->unsignedBigInteger('technical_warehouse_id');
            $table->string('technical_warehouse_name');
            $table->unsignedBigInteger('sales_warehouse_id');
            $table->string('sales_warehouse_name');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
