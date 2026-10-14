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
        Schema::create('sale_return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_item_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('invoice_number');
            $table->string('sku');
            $table->string('product_name');
            $table->string('brand')->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('delivered_quantity', 18, 3);
            $table->decimal('previously_returned_quantity', 18, 3)->default(0);
            $table->decimal('requested_quantity', 18, 3);
            $table->decimal('accepted_quantity', 18, 3)->default(0);
            $table->decimal('rejected_quantity', 18, 3)->default(0);
            $table->string('quality_status', 30)->default('PENDING');
            $table->text('assessment_note')->nullable();
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->string('technical_receive_reference')->nullable();
            $table->string('main_transfer_reference')->nullable();
            $table->unique(['sale_return_request_id', 'invoice_item_id'], 'srr_item_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_request_items');
    }
};
