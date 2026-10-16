<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kho bãi do inventory-service quản lý; bảng warehouses của catalog không còn dùng.
     */
    public function up(): void
    {
        Schema::dropIfExists('warehouses');
    }

    public function down(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('address', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
