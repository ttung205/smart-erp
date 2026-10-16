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
        Schema::create('office_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('kind', 16)->index();
            $table->string('category', 40);
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_name', 150);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('supplier_name', 200)->nullable();
            $table->text('purpose');
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 18, 2);
            $table->string('status', 20)->default('DRAFT')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('office_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_request_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('unit', 50);
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('office_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_request_id')->constrained()->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->string('disk_path', 500);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('office_request_attachments');
        Schema::dropIfExists('office_request_items');
        Schema::dropIfExists('office_requests');
    }
};
