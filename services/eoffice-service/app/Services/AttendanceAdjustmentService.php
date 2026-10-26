<?php

namespace App\Services;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceDay;
use App\Models\EmployeeProfile;
use App\Models\PunchLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HR thêm / xóa công thủ công. Không bao giờ sửa log thô: chỉ thêm log ADJUSTMENT hoặc đánh dấu hủy (voided_at),
 * mọi thao tác đều bắt buộc có lý do và được ghi vào attendance_audit_logs.
 */
class AttendanceAdjustmentService
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /** Thêm giờ vào/ra cho một ngày (bù công thủ công). Cần ít nhất một mốc giờ. */
    public function addWork(int $userId, string $date, ?string $in, ?string $out, string $reason, object $actor): AttendanceDay
    {
        $date = CarbonImmutable::parse($date)->toDateString();
        if (! $in && ! $out) {
            throw ValidationException::withMessages(['in' => 'Nhập giờ vào hoặc giờ ra cần thêm.']);
        }
        if ($in && $out && strcmp($out, $in) <= 0) {
            throw ValidationException::withMessages(['out' => 'Giờ ra phải sau giờ vào.']);
        }
        if (CarbonImmutable::parse($date)->isAfter(today())) {
            throw ValidationException::withMessages(['date' => 'Không thể thêm công cho ngày chưa diễn ra.']);
        }
        $this->ensureEmployee($userId);
        $this->attendance->assertUnlocked($userId, $date);

        return DB::transaction(function () use ($userId, $date, $in, $out, $reason, $actor) {
            $before = $this->snapshot($userId, $date);
            foreach (array_filter([$in, $out]) as $time) {
                PunchLog::firstOrCreate(
                    ['user_id' => $userId, 'punched_at' => CarbonImmutable::parse("{$date} {$time}"), 'source' => PunchLog::ADJUSTMENT],
                    ['work_date' => $date, 'note' => 'HR: '.$reason],
                );
            }
            $day = $this->attendance->recalculate($userId, $date);
            $this->audit('ADD_WORK', $userId, $date, $actor, $reason, $before, $this->snapshot($userId, $date));

            return $day;
        });
    }

    /** Xóa công của cả ngày: hủy mọi log chưa hủy của ngày đó (kể cả log máy chấm công). */
    public function clearDay(int $userId, string $date, string $reason, object $actor): AttendanceDay
    {
        $date = CarbonImmutable::parse($date)->toDateString();
        $this->attendance->assertUnlocked($userId, $date);

        return DB::transaction(function () use ($userId, $date, $reason, $actor) {
            $before = $this->snapshot($userId, $date);
            PunchLog::where('user_id', $userId)->whereDate('work_date', $date)->whereNull('voided_at')
                ->update(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => mb_substr($reason, 0, 255)]);
            $day = $this->attendance->recalculate($userId, $date);
            $this->audit('REMOVE_WORK', $userId, $date, $actor, $reason, $before, $this->snapshot($userId, $date));

            return $day;
        });
    }

    /** Hủy một log chấm công cụ thể (chấm nhầm, trùng). */
    public function voidPunch(PunchLog $log, string $reason, object $actor): AttendanceDay
    {
        if ($log->voided_at) {
            throw ValidationException::withMessages(['punch' => 'Log chấm công này đã bị hủy.']);
        }
        $date = $log->work_date->toDateString();
        $this->attendance->assertUnlocked($log->user_id, $date);

        return DB::transaction(function () use ($log, $date, $reason, $actor) {
            $before = $this->snapshot($log->user_id, $date);
            $log->update(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => mb_substr($reason, 0, 255)]);
            $day = $this->attendance->recalculate($log->user_id, $date);
            $this->audit('VOID_PUNCH', $log->user_id, $date, $actor, $reason, $before, $this->snapshot($log->user_id, $date));

            return $day;
        });
    }

    private function ensureEmployee(int $userId): void
    {
        if (! EmployeeProfile::where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Nhân viên chưa có hồ sơ chấm công.']);
        }
    }

    /** @return array<string, mixed>|null */
    private function snapshot(int $userId, string $date): ?array
    {
        $day = AttendanceDay::where('user_id', $userId)->whereDate('work_date', $date)->first();

        return $day?->only(['status', 'first_in', 'last_out', 'worked_minutes', 'late_minutes', 'early_minutes', 'work_value']);
    }

    private function audit(string $action, int $userId, string $date, object $actor, string $reason, ?array $before, ?array $after): void
    {
        AttendanceAuditLog::create([
            'user_id' => $userId, 'work_date' => $date, 'action' => $action, 'actor_id' => $actor->id,
            'actor_name' => $actor->name, 'reason' => mb_substr($reason, 0, 500), 'before' => $before, 'after' => $after,
        ]);
    }
}
