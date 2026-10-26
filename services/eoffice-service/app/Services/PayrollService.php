<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\EmployeeProfile;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRule;
use App\Models\PayrollSlip;
use App\Models\Shift;
use App\Support\Outbox;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vòng đời kỳ lương: khóa công → tính lương → gửi duyệt → duyệt/trả lại → (chi lương: {@see FinancePayrollService}).
 * Slip chỉ đổi được khi kỳ chưa gửi duyệt; sau khi duyệt, số liệu bất biến.
 */
class PayrollService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly PayrollCalculator $calculator,
        private readonly ApprovalService $approvals,
    ) {}

    public function ensurePeriod(int $year, int $month): PayrollPeriod
    {
        return PayrollPeriod::firstOrCreate(['year' => $year, 'month' => $month], ['status' => PayrollPeriod::DRAFT]);
    }

    /**
     * Chốt công của tháng: tính lại toàn bộ rồi khóa. Chỉ làm được khi tháng đã kết thúc.
     *
     * @return array{missing_punch_days: int, pending_days: int}
     */
    public function lockAttendance(PayrollPeriod $period, object $actor): array
    {
        $this->ensureOpen($period);
        [$start, $end] = $this->monthRange($period);
        if (CarbonImmutable::today()->lte($end)) {
            throw ValidationException::withMessages(['month' => "Chỉ khóa công khi tháng {$period->label()} đã kết thúc."]);
        }

        return DB::transaction(function () use ($period, $actor, $start, $end) {
            $userIds = $this->employeesOf($start, $end)->pluck('user_id');
            foreach ($userIds as $userId) {
                $this->attendance->recalculateMonth($userId, $start->format('Y-m'));
            }
            $days = AttendanceDay::whereIn('user_id', $userIds)->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);
            $summary = [
                'missing_punch_days' => (clone $days)->where('status', AttendanceDay::MISSING_PUNCH)->count(),
                'pending_days' => (clone $days)->where('status', AttendanceDay::PENDING)->count(),
            ];
            $days->update(['locked_at' => now()]);

            $period->update(['attendance_locked_at' => now(), 'attendance_locked_by' => $actor->id]);
            $this->approvals->record($period, $actor, 'ATTENDANCE_LOCKED');

            return $summary;
        });
    }

    public function unlockAttendance(PayrollPeriod $period, object $actor, string $reason): PayrollPeriod
    {
        $this->ensureOpen($period);
        if (! $period->attendance_locked_at) {
            throw ValidationException::withMessages(['period' => 'Công của kỳ này chưa được khóa.']);
        }

        return DB::transaction(function () use ($period, $actor, $reason) {
            [$start, $end] = $this->monthRange($period);
            AttendanceDay::whereBetween('work_date', [$start->toDateString(), $end->toDateString()])->update(['locked_at' => null]);
            // Số liệu lương cũ không còn khớp với công sẽ sửa: phải tính lại
            $period->slips()->delete();
            $period->update(['attendance_locked_at' => null, 'attendance_locked_by' => null, 'status' => PayrollPeriod::DRAFT, 'calculated_at' => null]);
            $this->approvals->record($period, $actor, 'ATTENDANCE_UNLOCKED', $reason);

            return $period->fresh();
        });
    }

    /**
     * Tính (hoặc tính lại) lương cho cả kỳ.
     *
     * @return array{period: PayrollPeriod, warnings: list<string>}
     */
    public function calculate(PayrollPeriod $period, object $actor): array
    {
        $this->ensureOpen($period);
        if (! $period->attendance_locked_at) {
            throw ValidationException::withMessages(['period' => 'Cần khóa công của tháng trước khi tính lương.']);
        }
        $rules = PayrollRule::allRules();
        if (! isset($rules['pit'], $rules['insurance'], $rules['ot_multipliers'])) {
            throw ValidationException::withMessages(['rules' => 'Chưa cấu hình đủ quy tắc lương (payroll_rules).']);
        }

        return DB::transaction(function () use ($period, $actor, $rules) {
            [$start, $end] = $this->monthRange($period);
            $adjustments = $period->adjustments()->get()->groupBy('user_id');
            $warnings = [];
            $kept = [];
            $standardByShift = [];

            foreach ($this->employeesOf($start, $end) as $profile) {
                if ($profile->base_salary <= 0) {
                    $warnings[] = "{$profile->user_name} ({$profile->employee_code}) chưa có lương cơ bản nên không được tính lương.";

                    continue;
                }
                $shift = $this->attendance->shiftFor($profile);
                $standard = $standardByShift[$shift->id] ??= $this->standardDays($shift, $start, $end);
                $days = AttendanceDay::where('user_id', $profile->user_id)->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])->get();
                $summary = $this->summarize($days, $standard);
                if ($summary['missing_punch_days'] > 0) {
                    $warnings[] = "{$profile->user_name}: còn {$summary['missing_punch_days']} ngày thiếu chấm công chưa được bổ sung (đang tính 0 công).";
                }

                $slip = $this->calculator->calculate([
                    'base_salary' => $profile->base_salary, 'fixed_allowance' => $profile->fixed_allowance,
                    'insurance_salary' => $profile->insurance_salary, 'dependents' => $profile->dependents,
                ], $summary['attendance'], $this->sumAdjustments($adjustments->get($profile->user_id, collect())), $rules);

                PayrollSlip::updateOrCreate(
                    ['payroll_period_id' => $period->id, 'user_id' => $profile->user_id],
                    ['user_name' => $profile->user_name, 'employee_code' => $profile->employee_code] + $slip,
                );
                $kept[] = $profile->user_id;
            }

            $period->slips()->whereNotIn('user_id', $kept)->delete();
            $slips = $period->slips()->get();
            $period->update([
                'status' => PayrollPeriod::CALCULATED, 'standard_days' => collect($standardByShift)->first() ?? 0,
                'employee_count' => $slips->count(), 'total_gross' => $slips->sum('gross_income'),
                'total_insurance' => $slips->sum('insurance_amount'), 'total_pit' => $slips->sum('pit_amount'),
                'total_net' => $slips->sum('net_pay'), 'rules_snapshot' => $rules,
                'calculated_at' => now(), 'calculated_by' => $actor->id, 'decision_comment' => null,
            ]);
            $this->approvals->record($period, $actor, 'CALCULATED');

            return ['period' => $period->fresh(), 'warnings' => $warnings];
        });
    }

    public function addAdjustment(PayrollPeriod $period, array $data, object $actor): PayrollAdjustment
    {
        $this->ensureOpen($period);
        if (! EmployeeProfile::where('user_id', $data['user_id'])->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Nhân viên chưa có hồ sơ chấm công/lương.']);
        }

        return DB::transaction(function () use ($period, $data, $actor) {
            $adjustment = $period->adjustments()->create([
                'user_id' => $data['user_id'], 'type' => $data['type'], 'amount' => $data['amount'], 'reason' => $data['reason'],
                'taxable' => $data['taxable'] ?? true, 'created_by' => $actor->id, 'created_by_name' => $actor->name,
            ]);
            $this->markStale($period);

            return $adjustment;
        });
    }

    public function removeAdjustment(PayrollAdjustment $adjustment, object $actor): void
    {
        $period = PayrollPeriod::findOrFail($adjustment->payroll_period_id);
        $this->ensureOpen($period);
        DB::transaction(function () use ($adjustment, $period) {
            $adjustment->delete();
            $this->markStale($period);
        });
    }

    public function submit(PayrollPeriod $period, object $actor): PayrollPeriod
    {
        if ($period->status !== PayrollPeriod::CALCULATED) {
            throw ValidationException::withMessages(['period' => 'Chỉ gửi duyệt được bảng lương đã tính và chưa thay đổi sau khi tính.']);
        }
        if ($period->slips()->count() === 0) {
            throw ValidationException::withMessages(['period' => 'Bảng lương chưa có phiếu lương nào.']);
        }

        return DB::transaction(function () use ($period, $actor) {
            $period->update(['status' => PayrollPeriod::PENDING_APPROVAL, 'submitted_at' => now(), 'submitted_by' => $actor->id]);
            $this->approvals->record($period, $actor, 'SUBMITTED');
            // Event không mang số tiền: chỉ báo "có bảng lương cần duyệt"
            $this->recordEvent('PayrollSubmitted', 'submitted', $period, ['actor' => Outbox::actor($actor)]);

            return $period->fresh();
        });
    }

    public function approve(PayrollPeriod $period, object $actor, ?string $comment): PayrollPeriod
    {
        $this->ensureDecidable($period, $actor);

        return DB::transaction(function () use ($period, $actor, $comment) {
            $period->update(['status' => PayrollPeriod::APPROVED, 'decided_at' => now(), 'decided_by' => $actor->id, 'decision_comment' => $comment]);
            $this->approvals->record($period, $actor, 'APPROVED', $comment);
            $this->recordEvent('PayrollApproved', 'approved', $period, ['actor' => Outbox::actor($actor)]);
            // Báo cho từng nhân viên biết phiếu lương đã có (không kèm số tiền)
            $this->recordEvent('PayslipPublished', 'slip_published', $period, [
                'actor' => Outbox::actor($actor), 'recipient_ids' => $period->slips()->pluck('user_id')->all(),
            ]);

            return $period->fresh();
        });
    }

    public function returnToPreparer(PayrollPeriod $period, object $actor, string $comment): PayrollPeriod
    {
        $this->ensureDecidable($period, $actor);

        return DB::transaction(function () use ($period, $actor, $comment) {
            $period->update(['status' => PayrollPeriod::RETURNED, 'decided_at' => now(), 'decided_by' => $actor->id, 'decision_comment' => $comment]);
            $this->approvals->record($period, $actor, 'RETURNED', $comment);
            $this->recordEvent('PayrollReturned', 'returned', $period, [
                'actor' => Outbox::actor($actor), 'comment' => $comment, 'recipient_ids' => array_values(array_filter([$period->submitted_by])),
            ]);

            return $period->fresh();
        });
    }

    /** Ghi sự kiện outbox của kỳ lương; payload không chứa số tiền. */
    public function recordEvent(string $eventType, string $action, PayrollPeriod $period, array $extra = []): void
    {
        Outbox::record($eventType, "eoffice.payroll.{$action}", 'PayrollPeriod', $period->id, [
            'period_id' => $period->id, 'year' => $period->year, 'month' => $period->month, 'label' => $period->label(),
            'employee_count' => $period->employee_count, ...$extra,
        ]);
    }

    private function ensureOpen(PayrollPeriod $period): void
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages(['period' => 'Bảng lương đã gửi duyệt hoặc đã duyệt nên không thể thay đổi.']);
        }
    }

    private function ensureDecidable(PayrollPeriod $period, object $actor): void
    {
        if ($period->status !== PayrollPeriod::PENDING_APPROVAL) {
            throw ValidationException::withMessages(['period' => 'Bảng lương không ở trạng thái chờ duyệt.']);
        }
        if ((int) $period->submitted_by === (int) $actor->id && ! config('eoffice.payroll_allow_self_approval')) {
            throw ValidationException::withMessages(['period' => 'Người lập bảng lương không được tự duyệt.']);
        }
    }

    /** Điều chỉnh đầu vào sau khi đã tính thì bảng lương không còn khớp: buộc tính lại trước khi gửi duyệt. */
    private function markStale(PayrollPeriod $period): void
    {
        if (in_array($period->status, [PayrollPeriod::CALCULATED, PayrollPeriod::RETURNED], true)) {
            $period->update(['status' => PayrollPeriod::DRAFT]);
        }
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function monthRange(PayrollPeriod $period): array
    {
        $start = CarbonImmutable::create($period->year, $period->month, 1)->startOfDay();

        return [$start, $start->endOfMonth()->startOfDay()];
    }

    /** @return Collection<int, EmployeeProfile> nhân viên đang làm trong tháng */
    private function employeesOf(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return EmployeeProfile::where('is_active', true)
            ->where(fn ($query) => $query->whereNull('hire_date')->orWhereDate('hire_date', '<=', $end->toDateString()))
            ->where(fn ($query) => $query->whereNull('termination_date')->orWhereDate('termination_date', '>=', $start->toDateString()))
            ->orderBy('employee_code')->get();
    }

    /** Công chuẩn của tháng theo ca: bỏ chủ nhật; thứ 7 theo chế độ của ca; ngày lễ rơi vào ngày làm việc vẫn tính. */
    public function standardDays(Shift $shift, CarbonImmutable $start, CarbonImmutable $end): float
    {
        $total = 0.0;
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $total += match (true) {
                $day->isSunday() => 0.0,
                $day->isSaturday() => match ($shift->saturday_mode) {
                    Shift::SATURDAY_FULL => 1.0, Shift::SATURDAY_HALF => 0.5, default => 0.0,
                },
                default => 1.0,
            };
        }

        return $total;
    }

    /**
     * @param  Collection<int, AttendanceDay>  $days
     * @return array{attendance: array<string, mixed>, missing_punch_days: int}
     */
    private function summarize(Collection $days, float $standard): array
    {
        $ot = ['WEEKDAY' => 0, 'WEEKEND' => 0, 'HOLIDAY' => 0];
        foreach ($days->where('ot_minutes', '>', 0) as $day) {
            $ot[$day->ot_type] = ($ot[$day->ot_type] ?? 0) + $day->ot_minutes;
        }

        return [
            'missing_punch_days' => $days->where('status', AttendanceDay::MISSING_PUNCH)->count(),
            'attendance' => [
                'standard_days' => $standard,
                'paid_days' => (float) $days->sum('work_value'),
                'unpaid_days' => (float) $days->where('status', AttendanceDay::LEAVE_UNPAID)->sum(fn (AttendanceDay $day) => 1 - $day->work_value),
                'absent_days' => $days->where('status', AttendanceDay::ABSENT)->count(),
                'late_count' => $days->where('late_minutes', '>', 0)->count(),
                'violation_count' => $days->filter(fn (AttendanceDay $day) => $day->late_minutes > 0 || $day->early_minutes > 0)->count(),
                'ot_minutes' => $ot,
            ],
        ];
    }

    /** @param  Collection<int, PayrollAdjustment>  $adjustments */
    private function sumAdjustments(Collection $adjustments): array
    {
        $bonuses = $adjustments->where('type', PayrollAdjustment::BONUS);

        return [
            'bonus' => (float) $bonuses->sum('amount'),
            'bonus_non_taxable' => (float) $bonuses->where('taxable', false)->sum('amount'),
            'deduction' => (float) $adjustments->where('type', PayrollAdjustment::DEDUCTION)->sum('amount'),
        ];
    }
}
