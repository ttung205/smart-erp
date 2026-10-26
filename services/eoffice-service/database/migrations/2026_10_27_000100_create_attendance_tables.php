<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            // Đến muộn tối đa bao nhiêu phút vẫn không bị tính trễ
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            // Số phút làm thực tế tối thiểu để được 1 công / 0,5 công
            $table->unsignedSmallInteger('full_day_minutes')->default(420);
            $table->unsignedSmallInteger('half_day_minutes')->default(240);
            // Thứ 7: OFF nghỉ, HALF làm nửa ngày (buổi sáng), FULL làm cả ngày
            $table->string('saturday_mode', 10)->default('OFF');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Hồ sơ nhân sự phục vụ chấm công / lương. identity-service không lưu các trường này.
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('user_name', 150);
            // Mã trên máy chấm công; file import khớp nhân viên theo mã này
            $table->string('employee_code', 30)->unique();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->decimal('base_salary', 15, 0)->default(0);
            $table->decimal('insurance_salary', 15, 0)->nullable();
            // Phụ cấp cố định hàng tháng (tính theo tỷ lệ công, chịu thuế, không đóng bảo hiểm)
            $table->decimal('fixed_allowance', 15, 0)->default(0);
            $table->unsignedTinyInteger('dependents')->default(0);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();
            // ONSITE chấm bằng máy / import; REMOTE, HYBRID được chấm công trên web
            $table->string('work_mode', 10)->default('ONSITE');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name', 255)->nullable();
            $table->string('source', 20);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->json('errors')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name', 150)->nullable();
            $table->timestamps();
        });

        // Log thô, không sửa: chỉ thêm mới hoặc đánh dấu hủy (voided_at)
        Schema::create('punch_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->dateTime('punched_at');
            $table->date('work_date');
            $table->string('source', 20);
            $table->string('device_id', 50)->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('attendance_import_batches')->nullOnDelete();
            $table->string('note', 255)->nullable();
            // Log ADJUSTMENT sinh ra từ yêu cầu bổ sung/bù công nào (để hủy khi yêu cầu bị hủy)
            $table->unsignedBigInteger('attendance_request_id')->nullable()->index();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            // Import lại cùng file không nhân đôi log
            $table->unique(['user_id', 'punched_at', 'source']);
            $table->index(['user_id', 'work_date']);
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('status', 20);
            $table->dateTime('first_in')->nullable();
            $table->dateTime('last_out')->nullable();
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_minutes')->default(0);
            $table->unsignedSmallInteger('ot_minutes')->default(0);
            $table->string('ot_type', 20)->nullable();
            $table->decimal('work_value', 3, 1)->default(0);
            $table->unsignedBigInteger('leave_request_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index('work_date');
        });

        Schema::create('attendance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('work_date')->nullable();
            $table->string('action', 30);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_audit_logs');
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('punch_logs');
        Schema::dropIfExists('attendance_import_batches');
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('shifts');
    }
};
