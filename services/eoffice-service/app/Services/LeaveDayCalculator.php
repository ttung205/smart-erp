<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

/**
 * Quy đổi khoảng nghỉ thành các "buổi" làm việc (Y-m-d:AM / Y-m-d:PM),
 * bỏ qua thứ 7, chủ nhật và ngày lễ. Mỗi buổi = 0,5 ngày công.
 */
class LeaveDayCalculator
{
    public const FULL = 'FULL';

    public const MORNING = 'MORNING';

    public const AFTERNOON = 'AFTERNOON';

    public const SESSIONS = [self::FULL, self::MORNING, self::AFTERNOON];

    public function totalDays(string $startDate, string $startSession, string $endDate, string $endSession): float
    {
        return count($this->slots($startDate, $startSession, $endDate, $endSession)) * 0.5;
    }

    /** @return list<string> */
    public function slots(string $startDate, string $startSession, string $endDate, string $endSession): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.']);
        }

        $singleDay = $start->equalTo($end);
        if (! $singleDay && $startSession === self::MORNING) {
            throw ValidationException::withMessages(['start_session' => 'Nghỉ nhiều ngày thì ngày đầu chỉ được chọn cả ngày hoặc buổi chiều.']);
        }
        if (! $singleDay && $endSession === self::AFTERNOON) {
            throw ValidationException::withMessages(['end_session' => 'Nghỉ nhiều ngày thì ngày cuối chỉ được chọn cả ngày hoặc buổi sáng.']);
        }

        $holidays = Holiday::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')->map(fn ($date) => $date->toDateString())->all();

        $slots = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $date = $day->toDateString();
            if ($day->isWeekend() || in_array($date, $holidays, true)) {
                continue;
            }

            $session = match (true) {
                $singleDay => $startSession,
                $date === $start->toDateString() => $startSession,
                $date === $end->toDateString() => $endSession,
                default => self::FULL,
            };
            if ($session !== self::AFTERNOON) {
                $slots[] = "{$date}:AM";
            }
            if ($session !== self::MORNING) {
                $slots[] = "{$date}:PM";
            }
        }

        return $slots;
    }
}
