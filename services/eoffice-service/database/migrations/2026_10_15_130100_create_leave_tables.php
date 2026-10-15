<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->boolean('is_paid')->default(true);
            $table->boolean('deducts_balance')->default(false);
            $table->decimal('max_days_per_request', 5, 1)->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('year');
            // Ngày bắt đầu tính phép trong năm: 01/01, ngày áp dụng hệ thống, hoặc ngày vào làm của nhân viên mới
            $table->date('period_start');
            // Số phép tại ngày đầu kỳ (HR nhập). Đã nghỉ / chờ duyệt được tính từ đơn nghỉ kể từ ngày đầu kỳ
            $table->decimal('opening_days', 5, 1)->default(12);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('user_name', 150);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->date('start_date');
            $table->string('start_session', 10)->default('FULL');
            $table->date('end_date');
            $table->string('end_session', 10)->default('FULL');
            $table->decimal('total_days', 5, 1);
            $table->text('reason');
            $table->unsignedBigInteger('handover_user_id')->nullable();
            $table->string('handover_user_name', 150)->nullable();
            $table->text('handover_note')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['department_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('leave_types');
    }
};
