<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\AttendanceRequest;
use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\PunchLog;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

/** Gom dữ liệu của một ngày từ database, chạy {@see AttendanceDayCalculator} và lưu kết quả vào attendance_days. */
class AttendanceService
{
    public function __construct(
        private readonly AttendanceDayCalculator $calculator,
        private readonly LeaveDayCalculator $leaveDays,
    ) {}

    public function shiftFor(?EmployeeProfile $profile): Shift
    {
        $shift = $profile?->shift_id ? Shift::find($profile->shift_id) : null;
        $shift ??= Shift::where('is_default', true)->where('is_active', true)->first() ?? Shift::where('is_active', true)->first();
        if (! $shift) {
            throw ValidationException::withMessages(['shift' => 'Chưa cấu hình ca làm việc nào.']);
        }

        return $shift;
    }

    /** Ngày đã khóa công: bản ghi ngày bị khóa, hoặc kỳ lương của tháng đó đã khóa công. */
    public function isLocked(int $userId, string|CarbonImmutable $date): bool
    {
        $date = CarbonImmutable::parse($date);
        if (AttendanceDay::where('user_id', $userId)->whereDate('work_date', $date->toDateString())->whereNotNull('locked_at')->exists()) {
            return true;
        }

        return PayrollPeriod::where('year', $date->year)->where('month', $date->month)->whereNotNull('attendance_locked_at')->exists();
    }

    public function assertUnlocked(int $userId, string|CarbonImmutable $date): void
    {
        if ($this->isLocked($userId, $date)) {
            $date = CarbonImmutable::parse($date);
            throw ValidationException::withMessages(['work_date' => "Công ngày {$date->format('d/m/Y')} đã được khóa cho kỳ lương, không thể thay đổi. Hãy dùng điều chỉnh lương kỳ sau hoặc nhờ người có quyền mở khóa."]);
        }
    }

    /**
     * Tính lại và lưu công của một ngày. Ngày đã khóa công giữ nguyên trừ khi $force.
     * Ngày tương lai không có dữ liệu thì không tạo bản ghi (trả về null).
     */
    public function recalculate(int $userId, string|CarbonImmutable $date, bool $force = false): ?AttendanceDay
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $existing = AttendanceDay::where('user_id', $userId)->whereDate('work_date', $date->toDateString())->first();
        if ($existing?->isLocked() && ! $force) {
            return $existing;
        }

        $today = CarbonImmutable::today();
        if ($date->isAfter($today) && ! $existing) {
            return null;
        }

        $profile = EmployeeProfile::where('user_id', $userId)->first();
        $shift = $this->shiftFor($profile);
        $punches = PunchLog::where('user_id', $userId)->whereDate('work_date', $date->toDateString())->whereNull('voided_at')
            ->orderBy('punched_at')->pluck('punched_at')
            ->map(fn ($time) => CarbonImmutable::instance($time))->all();
        $holiday = Holiday::whereDate('date', $date->toDateString())->value('name');
        [$workType, $overtimeWindows] = $this->approvedRequestsOn($userId, $date);

        $result = $this->calculator->calculate(
            $date, $shift, $punches, $holiday, $this->leaveOn($userId, $date), $today,
            $this->isEmployed($profile, $date), $workType, $overtimeWindows,
        );

        return AttendanceDay::updateOrCreate(
            ['user_id' => $userId, 'work_date' => $date->toDateString()],
            [
                'shift_id' => $shift->id, 'status' => $result['status'],
                'first_in' => $result['first_in'], 'last_out' => $result['last_out'],
                'worked_minutes' => $result['worked_minutes'], 'late_minutes' => $result['late_minutes'],
                'early_minutes' => $result['early_minutes'], 'work_value' => $result['work_value'],
                'ot_minutes' => $result['ot_minutes'], 'ot_type' => $result['ot_type'],
                'leave_request_id' => $result['leave_request_id'], 'note' => $result['note'], 'calculated_at' => now(),
            ],
        );
    }

    /** Tính lại cả tháng (đến hết hôm nay) cho một nhân viên. */
    public function recalculateMonth(int $userId, string $month, bool $force = false): int
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $end = $start->endOfMonth()->min(CarbonImmutable::today());
        $count = 0;
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $this->recalculate($userId, CarbonImmutable::instance($day), $force);
            $count++;
        }

        return $count;
    }

    private function isEmployed(?EmployeeProfile $profile, CarbonImmutable $date): bool
    {
        if (! $profile) {
            return true;
        }
        if ($profile->hire_date && $date->isBefore($profile->hire_date->startOfDay())) {
            return false;
        }

        return ! ($profile->termination_date && $date->isAfter($profile->termination_date->startOfDay()));
    }

    /**
     * Yêu cầu đã duyệt tác động tới ngày này: loại làm việc (REMOTE / BUSINESS_TRIP) và các khung giờ OT.
     *
     * @return array{0: ?string, 1: list<array{0: CarbonImmutable, 1: CarbonImmutable}>}
     */
    private function approvedRequestsOn(int $userId, CarbonImmutable $date): array
    {
        $requests = AttendanceRequest::where('user_id', $userId)->where('status', AttendanceRequest::APPROVED)
            ->whereIn('type', [AttendanceRequest::REMOTE, AttendanceRequest::BUSINESS_TRIP, AttendanceRequest::OVERTIME])
            ->whereDate('start_date', '<=', $date->toDateString())->whereDate('end_date', '>=', $date->toDateString())->get();

        $workType = null;
        $windows = [];
        foreach ($requests as $request) {
            if ($request->type === AttendanceRequest::OVERTIME) {
                $windows[] = [
                    CarbonImmutable::parse($date->toDateString().' '.$request->requested_in),
                    CarbonImmutable::parse($date->toDateString().' '.$request->requested_out),
                ];
            } else {
                // Công tác ưu tiên hơn làm việc từ xa nếu cùng ngày có cả hai
                $workType = $request->type === AttendanceRequest::BUSINESS_TRIP || $workType === null ? $request->type : $workType;
            }
        }

        return [$workType, $windows];
    }

    /** @return array{paid: bool, slots: list<string>, request_id: int|null}|null */
    private function leaveOn(int $userId, CarbonImmutable $date): ?array
    {
        $requests = LeaveRequest::with('leaveType')->where('user_id', $userId)->where('status', LeaveRequest::APPROVED)
            ->whereDate('start_date', '<=', $date->toDateString())->whereDate('end_date', '>=', $date->toDateString())->get();
        if ($requests->isEmpty()) {
            return null;
        }

        $slots = [];
        $paid = true;
        $requestId = null;
        foreach ($requests as $request) {
            $covered = array_filter(
                $this->leaveDays->slots($request->start_date->toDateString(), $request->start_session, $request->end_date->toDateString(), $request->end_session),
                fn (string $slot) => str_starts_with($slot, $date->toDateString()),
            );
            foreach ($covered as $slot) {
                $slots[] = substr($slot, -2);
            }
            if ($covered !== []) {
                $paid = $paid && (bool) $request->leaveType?->is_paid;
                $requestId ??= $request->id;
            }
        }

        return $slots === [] ? null : ['paid' => $paid, 'slots' => array_values(array_unique($slots)), 'request_id' => $requestId];
    }
}
