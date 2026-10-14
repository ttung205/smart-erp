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
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_return_request_item_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('invoice_item_id');
            $table->unsignedBigInteger('product_id');
            $table->string('invoice_number');
            $table->string('sku');
            $table->string('product_name');
            $table->string('brand')->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};
