<?php

namespace Tests\Unit;

use App\Models\AttendanceDay;
use App\Models\Shift;
use App\Services\AttendanceDayCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Mỗi test là một tình huống trong bảng kịch bản chấm công. Hôm nay giả định là thứ ba 27/10/2026. */
class AttendanceDayCalculatorTest extends TestCase
{
    private AttendanceDayCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new AttendanceDayCalculator;
    }

    private function shift(string $saturday = Shift::SATURDAY_OFF): Shift
    {
        return new Shift([
            'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00',
            'grace_minutes' => 10, 'full_day_minutes' => 420, 'half_day_minutes' => 240, 'saturday_mode' => $saturday,
        ]);
    }

    /** @param  list<string>  $times  giờ chấm dạng H:i trong ngày $date */
    private function day(string $date, array $times, ?string $holiday = null, ?array $leave = null, string $saturday = Shift::SATURDAY_OFF, bool $employed = true): array
    {
        $punches = array_map(fn (string $time) => CarbonImmutable::parse("{$date} {$time}"), $times);

        return $this->calculator->calculate(CarbonImmutable::parse($date), $this->shift($saturday), $punches, $holiday, $leave, CarbonImmutable::parse('2026-10-27'), $employed);
    }

    public function test_normal_day_counts_one_work_day_without_penalty(): void
    {
        $result = $this->day('2026-10-26', ['07:58', '17:05']);

        $this->assertSame(AttendanceDay::PRESENT, $result['status']);
        $this->assertSame(1.0, $result['work_value']);
        $this->assertSame(487, $result['worked_minutes']);
        $this->assertSame(0, $result['late_minutes']);
        $this->assertSame(0, $result['early_minutes']);
    }

    public function test_arrival_within_grace_is_not_late_but_beyond_grace_is_late_from_shift_start(): void
    {
        $this->assertSame(0, $this->day('2026-10-26', ['08:10', '17:00'])['late_minutes']);

        $late = $this->day('2026-10-26', ['08:25', '17:00']);
        $this->assertSame(25, $late['late_minutes']);
        $this->assertSame(1.0, $late['work_value'], 'Trễ ít vẫn đủ công, chỉ bị phạt theo quy tắc lương');
    }

    public function test_very_late_arrival_drops_to_half_day(): void
    {
        $result = $this->day('2026-10-26', ['09:40', '17:00']);

        $this->assertSame(AttendanceDay::HALF_DAY, $result['status']);
        $this->assertSame(0.5, $result['work_value']);
        $this->assertSame(100, $result['late_minutes']);
    }

    public function test_early_leave_records_minutes_and_can_drop_to_half_day(): void
    {
        $result = $this->day('2026-10-26', ['08:00', '15:00']);

        $this->assertSame(120, $result['early_minutes']);
        $this->assertSame(AttendanceDay::HALF_DAY, $result['status']);
        $this->assertSame(0.5, $result['work_value']);
    }

    public function test_lunch_break_is_not_counted_as_work(): void
    {
        $result = $this->day('2026-10-26', ['08:00', '12:30']);

        $this->assertSame(240, $result['worked_minutes']);
        $this->assertSame(AttendanceDay::HALF_DAY, $result['status']);
    }

    public function test_too_short_presence_is_not_counted(): void
    {
        $result = $this->day('2026-10-26', ['08:00', '10:00']);

        $this->assertSame(AttendanceDay::ABSENT, $result['status']);
        $this->assertSame(0.0, $result['work_value']);
    }

    public function test_single_punch_on_a_past_day_is_missing_punch_and_not_counted(): void
    {
        $result = $this->day('2026-10-26', ['08:02']);

        $this->assertSame(AttendanceDay::MISSING_PUNCH, $result['status']);
        $this->assertSame(0.0, $result['work_value']);
        $this->assertNotNull($result['first_in']);
        $this->assertNull($result['last_out']);
    }

    public function test_single_punch_today_is_still_pending(): void
    {
        $this->assertSame(AttendanceDay::PENDING, $this->day('2026-10-27', ['08:02'])['status']);
        $this->assertSame(AttendanceDay::PENDING, $this->day('2026-10-27', [])['status']);
    }

    public function test_no_punch_on_a_past_working_day_is_absent_and_future_day_is_pending(): void
    {
        $this->assertSame(AttendanceDay::ABSENT, $this->day('2026-10-26', [])['status']);
        $this->assertSame(AttendanceDay::PENDING, $this->day('2026-10-28', [])['status']);
    }

    public function test_noisy_duplicate_punches_are_merged(): void
    {
        $result = $this->day('2026-10-26', ['08:00', '08:01', '12:05', '17:00', '17:01']);

        $this->assertSame('08:00', $result['first_in']->format('H:i'));
        $this->assertSame('17:00', $result['last_out']->format('H:i'));
        $this->assertSame(AttendanceDay::PRESENT, $result['status']);
    }

    public function test_sunday_and_default_saturday_are_weekend(): void
    {
        $this->assertSame(AttendanceDay::WEEKEND, $this->day('2026-10-25', [])['status']);
        $this->assertSame(AttendanceDay::WEEKEND, $this->day('2026-10-24', [])['status']);
    }

    public function test_half_day_saturday_counts_half_a_day(): void
    {
        $result = $this->day('2026-10-24', ['08:00', '12:00'], saturday: Shift::SATURDAY_HALF);

        $this->assertSame(AttendanceDay::HALF_DAY, $result['status']);
        $this->assertSame(0.5, $result['work_value']);
        $this->assertSame(0, $result['early_minutes']);
    }

    public function test_working_day_holiday_is_a_paid_day_without_punches(): void
    {
        $result = $this->day('2026-10-26', [], holiday: 'Ngày lễ thử nghiệm');

        $this->assertSame(AttendanceDay::HOLIDAY, $result['status']);
        $this->assertSame(1.0, $result['work_value']);
        $this->assertSame('Ngày lễ thử nghiệm', $result['note']);
    }

    public function test_approved_paid_leave_counts_a_full_day_and_unpaid_leave_counts_nothing(): void
    {
        $paid = $this->day('2026-10-26', [], leave: ['paid' => true, 'slots' => ['AM', 'PM'], 'request_id' => 7]);
        $this->assertSame(AttendanceDay::LEAVE_PAID, $paid['status']);
        $this->assertSame(1.0, $paid['work_value']);
        $this->assertSame(7, $paid['leave_request_id']);

        $unpaid = $this->day('2026-10-26', [], leave: ['paid' => false, 'slots' => ['AM', 'PM'], 'request_id' => 8]);
        $this->assertSame(AttendanceDay::LEAVE_UNPAID, $unpaid['status']);
        $this->assertSame(0.0, $unpaid['work_value']);
    }

    public function test_half_day_paid_leave_plus_worked_other_half_makes_a_full_day(): void
    {
        $leave = ['paid' => true, 'slots' => ['AM'], 'request_id' => 9];

        $this->assertSame(1.0, $this->day('2026-10-26', ['13:00', '17:00'], leave: $leave)['work_value']);
        $this->assertSame(0.5, $this->day('2026-10-26', [], leave: $leave)['work_value']);
    }

    public function test_days_outside_employment_are_not_counted(): void
    {
        $this->assertSame(AttendanceDay::OUT_OF_EMPLOYMENT, $this->day('2026-10-26', ['08:00', '17:00'], employed: false)['status']);
    }

    #[DataProvider('shiftRanges')]
    public function test_shift_standard_minutes(string $break, int $expected): void
    {
        $shift = $this->shift();
        $shift->break_end = $break;

        $this->assertSame($expected, $shift->standardMinutes());
    }

    /** @return array<string, array{string, int}> */
    public static function shiftRanges(): array
    {
        return ['nghỉ trưa 1 giờ' => ['13:00:00', 480], 'nghỉ trưa 1,5 giờ' => ['13:30:00', 450]];
    }
}
