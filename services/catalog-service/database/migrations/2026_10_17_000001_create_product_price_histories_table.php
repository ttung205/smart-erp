<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->decimal('old_gross_price', 18, 2)->nullable();
            $table->decimal('new_gross_price', 18, 2);
            $table->decimal('old_cost_price', 18, 2)->nullable();
            $table->decimal('new_cost_price', 18, 2)->nullable();
            // INQUIRY | MANUAL | IMPORT | CREATE
            $table->string('source', 30);
            $table->string('reference', 100)->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
    }
};
