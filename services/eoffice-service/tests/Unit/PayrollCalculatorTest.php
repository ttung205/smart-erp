<?php

namespace Tests\Unit;

use App\Services\PayrollCalculator;
use PHPUnit\Framework\TestCase;

/** Số liệu tính tay độc lập với code để đối chiếu công thức lương. */
class PayrollCalculatorTest extends TestCase
{
    private function rules(array $override = []): array
    {
        return $override + [
            'standard' => ['hours_per_day' => 8, 'round_to' => 1],
            'ot_multipliers' => ['WEEKDAY' => 1.5, 'WEEKEND' => 2.0, 'HOLIDAY' => 3.0],
            'late_penalty' => ['free_count' => 2, 'amount_per_violation' => 50000],
            'insurance' => ['employee_rate' => 0.105, 'salary_cap' => 46800000],
            'pit' => [
                'personal_deduction' => 11000000, 'dependent_deduction' => 4400000,
                'brackets' => [
                    ['up_to' => 5000000, 'rate' => 0.05], ['up_to' => 10000000, 'rate' => 0.10], ['up_to' => 18000000, 'rate' => 0.15],
                    ['up_to' => 32000000, 'rate' => 0.20], ['up_to' => 52000000, 'rate' => 0.25], ['up_to' => 80000000, 'rate' => 0.30],
                    ['up_to' => null, 'rate' => 0.35],
                ],
            ],
        ];
    }

    private function employee(float $base, float $allowance = 0, int $dependents = 0, ?float $insuranceSalary = null): array
    {
        return ['base_salary' => $base, 'fixed_allowance' => $allowance, 'insurance_salary' => $insuranceSalary, 'dependents' => $dependents];
    }

    private function attendance(float $standard = 22, float $paid = 22, int $violations = 0, int $weekdayOt = 0, int $weekendOt = 0, int $holidayOt = 0, float $unpaid = 0, int $absent = 0): array
    {
        return [
            'standard_days' => $standard, 'paid_days' => $paid, 'unpaid_days' => $unpaid, 'absent_days' => $absent,
            'late_count' => $violations, 'violation_count' => $violations,
            'ot_minutes' => ['WEEKDAY' => $weekdayOt, 'WEEKEND' => $weekendOt, 'HOLIDAY' => $holidayOt],
        ];
    }

    private function noAdjustments(): array
    {
        return ['bonus' => 0.0, 'bonus_non_taxable' => 0.0, 'deduction' => 0.0];
    }

    public function test_prorated_salary_overtime_penalty_and_no_tax_below_the_threshold(): void
    {
        // 15tr, phụ cấp 1tr, đi làm 21/22 ngày, OT 2 giờ ngày thường + 4 giờ ngày nghỉ, 4 lần vi phạm, 1 người phụ thuộc
        $slip = (new PayrollCalculator)->calculate(
            $this->employee(15000000, 1000000, 1),
            $this->attendance(22, 21, violations: 4, weekdayOt: 120, weekendOt: 240),
            $this->noAdjustments(),
            $this->rules(),
        );

        $this->assertSame(14318182.0, $slip['salary_amount']);   // 15tr × 21/22
        $this->assertSame(954545.0, $slip['allowance_amount']);  // 1tr × 21/22
        $this->assertSame(937500.0, $slip['ot_amount']);         // 85.227,27 × (2×1,5 + 4×2)
        $this->assertSame(2.0, $slip['ot_hours_weekday']);
        $this->assertSame(4.0, $slip['ot_hours_weekend']);
        $this->assertSame(16210227.0, $slip['gross_income']);
        $this->assertSame(1575000.0, $slip['insurance_amount']); // 10,5% × 15tr
        $this->assertSame(0.0, $slip['taxable_income']);         // 16,21tr − 1,575tr − 11tr − 4,4tr < 0
        $this->assertSame(0.0, $slip['pit_amount']);
        $this->assertSame(100000.0, $slip['penalty_amount']);    // (4 − 2 lần miễn) × 50.000
        $this->assertSame(14535227.0, $slip['net_pay']);
    }

    public function test_progressive_personal_income_tax(): void
    {
        $slip = (new PayrollCalculator)->calculate($this->employee(40000000), $this->attendance(), $this->noAdjustments(), $this->rules());

        $this->assertSame(40000000.0, $slip['gross_income']);
        $this->assertSame(4200000.0, $slip['insurance_amount']);
        $this->assertSame(24800000.0, $slip['taxable_income']);  // 40tr − 4,2tr − 11tr
        // 5tr×5% + 5tr×10% + 8tr×15% + 6,8tr×20%
        $this->assertSame(3310000.0, $slip['pit_amount']);
        $this->assertSame(32490000.0, $slip['net_pay']);
        $this->assertCount(4, $slip['breakdown']['pit_brackets']);
    }

    public function test_insurance_uses_the_insurance_salary_and_respects_the_cap(): void
    {
        $custom = (new PayrollCalculator)->calculate($this->employee(20000000, insuranceSalary: 10000000), $this->attendance(), $this->noAdjustments(), $this->rules());
        $this->assertSame(1050000.0, $custom['insurance_amount']);

        $capped = (new PayrollCalculator)->calculate($this->employee(100000000), $this->attendance(), $this->noAdjustments(), $this->rules());
        $this->assertSame(4914000.0, $capped['insurance_amount']); // 10,5% × 46,8tr
    }

    public function test_unpaid_leave_and_absence_reduce_salary_and_paid_days_cannot_exceed_standard(): void
    {
        $short = (new PayrollCalculator)->calculate($this->employee(22000000), $this->attendance(22, 20, unpaid: 1, absent: 1), $this->noAdjustments(), $this->rules());
        $this->assertSame(20000000.0, $short['salary_amount']);

        $over = (new PayrollCalculator)->calculate($this->employee(22000000), $this->attendance(22, 23), $this->noAdjustments(), $this->rules());
        $this->assertSame(22000000.0, $over['salary_amount'], 'Công hưởng lương tối đa bằng công chuẩn');
        $this->assertSame(22.0, $over['paid_days']);
    }

    public function test_holiday_overtime_uses_the_triple_rate(): void
    {
        // 22tr ÷ 22 ngày ÷ 8 giờ = 125.000/giờ × 8 giờ × 3
        $slip = (new PayrollCalculator)->calculate($this->employee(22000000), $this->attendance(holidayOt: 480), $this->noAdjustments(), $this->rules());

        $this->assertSame(3000000.0, $slip['ot_amount']);
        $this->assertSame(8.0, $slip['ot_hours_holiday']);
    }

    public function test_manual_bonus_and_deduction_and_non_taxable_bonus(): void
    {
        $adjustments = ['bonus' => 3000000.0, 'bonus_non_taxable' => 1000000.0, 'deduction' => 500000.0];
        $slip = (new PayrollCalculator)->calculate($this->employee(30000000), $this->attendance(), $adjustments, $this->rules());

        $this->assertSame(33000000.0, $slip['gross_income']);
        $this->assertSame(3150000.0, $slip['insurance_amount']);
        // 33tr − 1tr không chịu thuế − 3,15tr − 11tr = 17,85tr: 5tr×5% + 5tr×10% + 7,85tr×15%
        $this->assertSame(17850000.0, $slip['taxable_income']);
        $this->assertSame(1927500.0, $slip['pit_amount']);
        $this->assertSame(33000000.0 - 3150000.0 - 1927500.0 - 500000.0, $slip['net_pay']);
    }

    public function test_net_pay_is_never_negative_and_the_shortfall_is_reported(): void
    {
        $slip = (new PayrollCalculator)->calculate($this->employee(5000000), $this->attendance(), ['bonus' => 0.0, 'bonus_non_taxable' => 0.0, 'deduction' => 9000000.0], $this->rules());

        $this->assertSame(0.0, $slip['net_pay']);
        $this->assertGreaterThan(0, $slip['breakdown']['shortfall']);
    }

    public function test_no_paid_days_means_no_salary_and_no_insurance(): void
    {
        $slip = (new PayrollCalculator)->calculate($this->employee(20000000), $this->attendance(22, 0, absent: 22), $this->noAdjustments(), $this->rules());

        $this->assertSame(0.0, $slip['salary_amount']);
        $this->assertSame(0.0, $slip['insurance_amount']);
        $this->assertSame(0.0, $slip['net_pay']);
    }
}
