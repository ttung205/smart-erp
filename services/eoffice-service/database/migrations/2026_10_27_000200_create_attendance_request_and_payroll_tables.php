<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Yêu cầu bổ sung công / bù công / remote / công tác / OT, đi qua luồng duyệt chung (approval_steps)
        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('type', 20);
            $table->unsignedBigInteger('user_id');
            $table->string('user_name', 150);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            // ADD_PUNCH/MAKEUP: giờ vào-ra cần ghi nhận; OVERTIME: giờ bắt đầu-kết thúc làm thêm
            $table->time('requested_in')->nullable();
            $table->time('requested_out')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
            $table->index(['status', 'type']);
        });

        Schema::create('payroll_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_key', 50)->unique();
            $table->json('value');
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            // DRAFT → CALCULATED → PENDING_APPROVAL → APPROVED → PAID (RETURNED khi bị trả lại)
            $table->string('status', 20)->default('DRAFT');
            $table->decimal('standard_days', 4, 1)->default(0);
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_gross', 16, 0)->default(0);
            $table->decimal('total_insurance', 16, 0)->default(0);
            $table->decimal('total_pit', 16, 0)->default(0);
            $table->decimal('total_net', 16, 0)->default(0);
            $table->json('rules_snapshot')->nullable();
            $table->timestamp('attendance_locked_at')->nullable();
            $table->unsignedBigInteger('attendance_locked_by')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->string('decision_comment', 1000)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            // Hạch toán sang finance-service: NONE | JOURNAL_POSTED | POSTED | FAILED
            $table->string('finance_status', 20)->default('NONE');
            $table->string('finance_reference', 255)->nullable();
            $table->text('finance_error')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('user_name', 150);
            $table->string('employee_code', 30);
            $table->decimal('base_salary', 15, 0)->default(0);
            $table->decimal('fixed_allowance', 15, 0)->default(0);
            $table->decimal('standard_days', 4, 1)->default(0);
            $table->decimal('paid_days', 4, 1)->default(0);
            $table->decimal('unpaid_days', 4, 1)->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->unsignedSmallInteger('violation_count')->default(0);
            $table->decimal('ot_hours_weekday', 6, 1)->default(0);
            $table->decimal('ot_hours_weekend', 6, 1)->default(0);
            $table->decimal('ot_hours_holiday', 6, 1)->default(0);
            $table->decimal('salary_amount', 15, 0)->default(0);
            $table->decimal('allowance_amount', 15, 0)->default(0);
            $table->decimal('ot_amount', 15, 0)->default(0);
            $table->decimal('bonus_amount', 15, 0)->default(0);
            $table->decimal('gross_income', 15, 0)->default(0);
            $table->decimal('insurance_amount', 15, 0)->default(0);
            $table->decimal('taxable_income', 15, 0)->default(0);
            $table->decimal('pit_amount', 15, 0)->default(0);
            $table->decimal('penalty_amount', 15, 0)->default(0);
            $table->decimal('deduction_amount', 15, 0)->default(0);
            $table->decimal('net_pay', 15, 0)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'user_id']);
            $table->index('user_id');
        });

        // Thưởng / khấu trừ thủ công theo kỳ lương, luôn có lý do
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 10);
            $table->decimal('amount', 15, 0);
            $table->string('reason', 500);
            $table->boolean('taxable')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name', 150)->nullable();
            $table->timestamps();

            $table->index(['payroll_period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_rules');
        Schema::dropIfExists('attendance_requests');
    }
};
