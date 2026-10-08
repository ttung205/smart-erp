<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_attachments')) {
            Schema::create('sales_attachments', function (Blueprint $table) {
                $table->id();
                $table->string('attachable_type', 50)->comment('quotation | sales_order | invoice');
                $table->unsignedBigInteger('attachable_id')->nullable()->comment('Foreign ID or null when temporarily uploaded');
                $table->string('file_name', 255);
                $table->string('file_path', 500);
                $table->string('file_url', 500)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('mime_type', 100)->nullable();
                $table->string('uploaded_by', 100)->nullable();
                $table->timestamps();

                $table->index(['attachable_type', 'attachable_id'], 'idx_sales_attachments_attachable');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_attachments');
    }
};
