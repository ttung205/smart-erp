<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\Shift;
use Carbon\CarbonImmutable;

/**
 * Hàm thuần: từ log chấm công, ca làm, ngày lễ, nghỉ phép của MỘT ngày → kết quả công của ngày đó.
 * Không đọc database nên chạy lại bao nhiêu lần cũng cho cùng kết quả (idempotent).
 *
 * Quy tắc chính:
 * - Chủ nhật luôn nghỉ; thứ 7 theo shifts.saturday_mode. Ngày lễ rơi vào ngày làm việc được tính 1 công có lương.
 * - Lần chấm đầu trong ngày là giờ vào, lần cuối là giờ ra; các lần chấm cách nhau dưới 2 phút bị gộp.
 * - Vào muộn quá thời gian ân hạn (grace) thì tính trễ từ giờ vào ca; công của ngày dựa trên số phút làm thực tế.
 * - Ngày đã qua mà chỉ có 1 lần chấm là MISSING_PUNCH (chưa tính công, chờ bổ sung).
 */
class AttendanceDayCalculator
{
    /** Hai lần chấm cách nhau dưới ngưỡng này được coi là cùng một lần. */
    public const DEDUPE_SECONDS = 120;

    public const OT_WEEKDAY = 'WEEKDAY';

    public const OT_WEEKEND = 'WEEKEND';

    public const OT_HOLIDAY = 'HOLIDAY';

    /** OT làm tròn xuống theo bước này (phút). */
    public const OT_STEP_MINUTES = 30;

    /**
     * @param  list<CarbonImmutable>  $punches  log của đúng ngày $date, chưa cần sắp xếp
     * @param  array{paid: bool, slots: list<string>, request_id: int|null}|null  $leave  slots gồm AM và/hoặc PM
     * @param  string|null  $workType  REMOTE hoặc BUSINESS_TRIP khi có yêu cầu đã duyệt cho ngày này
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $overtimeWindows  khung giờ OT đã duyệt trong ngày
     * @return array{status: string, first_in: ?CarbonImmutable, last_out: ?CarbonImmutable, worked_minutes: int, late_minutes: int, early_minutes: int, work_value: float, leave_request_id: ?int, note: ?string, ot_minutes: int, ot_type: ?string}
     */
    public function calculate(CarbonImmutable $date, Shift $shift, array $punches, ?string $holidayName, ?array $leave, CarbonImmutable $today, bool $employed = true, ?string $workType = null, array $overtimeWindows = []): array
    {
        $result = $this->dayResult($date, $shift, $punches, $holidayName, $leave, $today, $employed, $workType);
        $result['ot_minutes'] = 0;
        $result['ot_type'] = null;

        if ($employed && $overtimeWindows !== [] && $result['first_in'] && $result['last_out']) {
            $minutes = $this->overtimeMinutes($overtimeWindows, $result['first_in'], $result['last_out']);
            if ($minutes > 0) {
                $isRestDay = $date->isSunday() || ($date->isSaturday() && $shift->saturday_mode === Shift::SATURDAY_OFF);
                $result['ot_minutes'] = $minutes;
                $result['ot_type'] = $holidayName !== null ? self::OT_HOLIDAY : ($isRestDay ? self::OT_WEEKEND : self::OT_WEEKDAY);
            }
        }

        return $result;
    }

    /**
     * Chỉ tính phần OT đã duyệt trước VÀ thực tế có mặt (nằm trong khoảng vào-ra), làm tròn xuống bội số của 30 phút.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows
     */
    private function overtimeMinutes(array $windows, CarbonImmutable $firstIn, CarbonImmutable $lastOut): int
    {
        $minutes = 0;
        foreach ($windows as [$start, $end]) {
            $from = $start->max($firstIn);
            $to = $end->min($lastOut);
            if ($to->greaterThan($from)) {
                $minutes += intdiv($to->getTimestamp() - $from->getTimestamp(), 60);
            }
        }

        return intdiv($minutes, self::OT_STEP_MINUTES) * self::OT_STEP_MINUTES;
    }

    private function dayResult(CarbonImmutable $date, Shift $shift, array $punches, ?string $holidayName, ?array $leave, CarbonImmutable $today, bool $employed, ?string $workType): array
    {
        $punches = $this->dedupe($punches);
        $firstIn = $punches[0] ?? null;
        $lastOut = count($punches) > 1 ? $punches[array_key_last($punches)] : null;
        $result = fn (string $status, float $value = 0.0, ?string $note = null, int $worked = 0, int $late = 0, int $early = 0) => [
            'status' => $status, 'first_in' => $firstIn, 'last_out' => $lastOut, 'worked_minutes' => $worked,
            'late_minutes' => $late, 'early_minutes' => $early, 'work_value' => $value,
            'leave_request_id' => $leave['request_id'] ?? null, 'note' => $note,
        ];

        if (! $employed) {
            return $result(AttendanceDay::OUT_OF_EMPLOYMENT);
        }
        if ($date->isSunday() || ($date->isSaturday() && $shift->saturday_mode === Shift::SATURDAY_OFF)) {
            return $result(AttendanceDay::WEEKEND);
        }
        if ($holidayName !== null) {
            return $result(AttendanceDay::HOLIDAY, 1.0, $holidayName);
        }

        $leaveSlots = $leave['slots'] ?? [];
        if (count($leaveSlots) >= 2) {
            return $leave['paid']
                ? $result(AttendanceDay::LEAVE_PAID, 1.0, 'Nghỉ phép có lương')
                : $result(AttendanceDay::LEAVE_UNPAID, 0.0, 'Nghỉ không lương');
        }
        if ($leaveSlots !== []) {
            return $this->halfLeaveDay($result, $leave['paid'], $punches !== []);
        }

        if ($workType === AttendanceDay::REMOTE) {
            return $result(AttendanceDay::REMOTE, 1.0, 'Làm việc từ xa');
        }
        if ($workType === AttendanceDay::BUSINESS_TRIP) {
            return $result(AttendanceDay::BUSINESS_TRIP, 1.0, 'Đi công tác');
        }
        if ($date->isAfter($today)) {
            return $result(AttendanceDay::PENDING);
        }
        if (count($punches) === 0) {
            return $date->isSameDay($today) ? $result(AttendanceDay::PENDING) : $result(AttendanceDay::ABSENT, 0.0, 'Không có dữ liệu chấm công');
        }
        if (count($punches) === 1) {
            return $date->isSameDay($today)
                ? $result(AttendanceDay::PENDING, 0.0, 'Đang làm việc')
                : $result(AttendanceDay::MISSING_PUNCH, 0.0, 'Chỉ có một lần chấm công, cần bổ sung');
        }

        return $this->workedDay($result, $shift, $firstIn, $lastOut);
    }

    /** Nghỉ nửa ngày: nửa nghỉ được tính theo loại phép, nửa còn lại cần có chấm công. */
    private function halfLeaveDay(callable $result, bool $paid, bool $hasPunch): array
    {
        $value = ($paid ? 0.5 : 0.0) + ($hasPunch ? 0.5 : 0.0);

        return $result($paid ? AttendanceDay::LEAVE_PAID : AttendanceDay::LEAVE_UNPAID, $value, $hasPunch ? 'Nghỉ nửa ngày' : 'Nghỉ nửa ngày, buổi còn lại không chấm công');
    }

    private function workedDay(callable $result, Shift $shift, CarbonImmutable $firstIn, CarbonImmutable $lastOut): array
    {
        $saturdayHalf = $firstIn->isSaturday() && $shift->saturday_mode === Shift::SATURDAY_HALF;
        $startMinutes = $shift->minutesOf($shift->start_time);
        $endMinutes = $saturdayHalf && $shift->break_start ? $shift->minutesOf($shift->break_start) : $shift->minutesOf($shift->end_time);

        $inMinutes = $firstIn->hour * 60 + $firstIn->minute;
        $outMinutes = $lastOut->hour * 60 + $lastOut->minute;
        $worked = max($outMinutes - $inMinutes - $this->breakOverlap($shift, $inMinutes, $outMinutes), 0);

        $late = $inMinutes > $startMinutes + $shift->grace_minutes ? $inMinutes - $startMinutes : 0;
        $early = max($endMinutes - $outMinutes, 0);

        if ($saturdayHalf) {
            return $worked >= min($shift->half_day_minutes, 180)
                ? $result(AttendanceDay::HALF_DAY, 0.5, 'Thứ 7 làm nửa ngày', $worked, $late, $early)
                : $result(AttendanceDay::ABSENT, 0.0, 'Thời gian làm quá ngắn', $worked, $late, $early);
        }
        if ($worked >= $shift->full_day_minutes) {
            return $result(AttendanceDay::PRESENT, 1.0, null, $worked, $late, $early);
        }
        if ($worked >= $shift->half_day_minutes) {
            return $result(AttendanceDay::HALF_DAY, 0.5, 'Làm chưa đủ ngày công', $worked, $late, $early);
        }

        return $result(AttendanceDay::ABSENT, 0.0, 'Thời gian làm quá ngắn', $worked, $late, $early);
    }

    /** Số phút giờ nghỉ trưa nằm trong khoảng [vào, ra]. */
    private function breakOverlap(Shift $shift, int $inMinutes, int $outMinutes): int
    {
        if (! $shift->break_start || ! $shift->break_end) {
            return 0;
        }

        return max(min($outMinutes, $shift->minutesOf($shift->break_end)) - max($inMinutes, $shift->minutesOf($shift->break_start)), 0);
    }

    /**
     * @param  list<CarbonImmutable>  $punches
     * @return list<CarbonImmutable>
     */
    private function dedupe(array $punches): array
    {
        usort($punches, fn (CarbonImmutable $a, CarbonImmutable $b) => $a->getTimestamp() <=> $b->getTimestamp());
        $kept = [];
        foreach ($punches as $punch) {
            $previous = $kept === [] ? null : $kept[array_key_last($kept)];
            if ($previous === null || $punch->getTimestamp() - $previous->getTimestamp() >= self::DEDUPE_SECONDS) {
                $kept[] = $punch;
            }
        }

        return $kept;
    }
}
