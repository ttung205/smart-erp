<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Transactional Outbox: ghi cùng transaction với nghiệp vụ, lệnh outbox:relay đẩy lên RabbitMQ
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 100)->unique();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 100);
            $table->string('aggregate_id', 100);
            $table->string('routing_key', 150);
            $table->longText('payload');
            $table->string('status', 30)->default('PENDING');
            $table->integer('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            // created_by đang lưu tên người tạo; cần ID để gửi thông báo kết quả duyệt
            $table->unsignedBigInteger('created_by_id')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('created_by_id');
        });
        Schema::dropIfExists('outbox_events');
    }
};
