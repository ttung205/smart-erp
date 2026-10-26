<?php

namespace App\Services;

/**
 * Hàm thuần: tính phiếu lương của MỘT nhân viên trong MỘT tháng từ công, giờ OT, điều chỉnh và bảng quy tắc.
 * Không đọc database; mọi tham số tiền/tỷ lệ nằm trong $rules (bảng payroll_rules) để đổi không cần deploy.
 *
 * Công thức:
 * - Lương theo công = lương cơ bản × công hưởng lương ÷ công chuẩn (công hưởng lương tối đa bằng công chuẩn); phụ cấp tính cùng tỷ lệ.
 * - OT = lương cơ bản ÷ công chuẩn ÷ giờ/ngày × giờ OT × hệ số theo loại ngày.
 * - Phạt: số lần vi phạm (ngày đi trễ hoặc về sớm) vượt số lần miễn phạt × mức phạt mỗi lần.
 * - Bảo hiểm người lao động = tỷ lệ × lương đóng BH (trần theo quy tắc).
 * - Thuế TNCN lũy tiến trên (thu nhập − bảo hiểm − giảm trừ bản thân − giảm trừ người phụ thuộc − khoản thưởng không chịu thuế).
 */
class PayrollCalculator
{
    /**
     * @param  array{base_salary: float, fixed_allowance: float, insurance_salary: ?float, dependents: int}  $employee
     * @param  array{standard_days: float, paid_days: float, unpaid_days: float, absent_days: int, late_count: int, violation_count: int, ot_minutes: array{WEEKDAY: int, WEEKEND: int, HOLIDAY: int}}  $attendance
     * @param  array{bonus: float, bonus_non_taxable: float, deduction: float}  $adjustments
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed> các cột của payroll_slips (trừ khóa) kèm breakdown
     */
    public function calculate(array $employee, array $attendance, array $adjustments, array $rules): array
    {
        $round = fn (float $amount): float => $this->round($amount, $rules);

        $standardDays = max((float) $attendance['standard_days'], 0.0);
        $paidDays = $standardDays > 0 ? min((float) $attendance['paid_days'], $standardDays) : 0.0;
        $ratio = $standardDays > 0 ? $paidDays / $standardDays : 0.0;
        $hoursPerDay = max((float) ($rules['standard']['hours_per_day'] ?? 8), 1.0);

        $salary = $round($employee['base_salary'] * $ratio);
        $allowance = $round($employee['fixed_allowance'] * $ratio);

        $hourlyRate = $standardDays > 0 ? $employee['base_salary'] / $standardDays / $hoursPerDay : 0.0;
        $overtime = [];
        $overtimeAmount = 0.0;
        foreach (['WEEKDAY', 'WEEKEND', 'HOLIDAY'] as $type) {
            $hours = round(($attendance['ot_minutes'][$type] ?? 0) / 60, 1);
            $multiplier = (float) ($rules['ot_multipliers'][$type] ?? 1.0);
            $amount = $round($hourlyRate * (($attendance['ot_minutes'][$type] ?? 0) / 60) * $multiplier);
            $overtime[$type] = ['hours' => $hours, 'multiplier' => $multiplier, 'amount' => $amount];
            $overtimeAmount += $amount;
        }

        $bonus = (float) $adjustments['bonus'];
        $gross = $salary + $allowance + $overtimeAmount + $bonus;

        $insurance = $paidDays > 0 ? $this->insurance($employee, $rules, $round) : 0.0;
        $personalDeduction = (float) ($rules['pit']['personal_deduction'] ?? 0);
        $dependentDeduction = (float) ($rules['pit']['dependent_deduction'] ?? 0) * $employee['dependents'];
        $taxable = max($gross - (float) $adjustments['bonus_non_taxable'] - $insurance - $personalDeduction - $dependentDeduction, 0.0);
        [$pit, $pitBrackets] = $this->personalIncomeTax($taxable, $rules['pit']['brackets'] ?? [], $round);

        $freeViolations = (int) ($rules['late_penalty']['free_count'] ?? 0);
        $penalty = $round(max($attendance['violation_count'] - $freeViolations, 0) * (float) ($rules['late_penalty']['amount_per_violation'] ?? 0));
        $deduction = (float) $adjustments['deduction'];

        $net = $gross - $insurance - $pit - $penalty - $deduction;

        return [
            'base_salary' => $employee['base_salary'], 'fixed_allowance' => $employee['fixed_allowance'],
            'standard_days' => $standardDays, 'paid_days' => $paidDays, 'unpaid_days' => (float) $attendance['unpaid_days'],
            'absent_days' => $attendance['absent_days'], 'late_count' => $attendance['late_count'], 'violation_count' => $attendance['violation_count'],
            'ot_hours_weekday' => $overtime['WEEKDAY']['hours'], 'ot_hours_weekend' => $overtime['WEEKEND']['hours'], 'ot_hours_holiday' => $overtime['HOLIDAY']['hours'],
            'salary_amount' => $salary, 'allowance_amount' => $allowance, 'ot_amount' => $overtimeAmount, 'bonus_amount' => $bonus,
            'gross_income' => $gross, 'insurance_amount' => $insurance, 'taxable_income' => $taxable, 'pit_amount' => $pit,
            'penalty_amount' => $penalty, 'deduction_amount' => $deduction, 'net_pay' => max($net, 0.0),
            'breakdown' => [
                'hourly_rate' => round($hourlyRate, 2),
                'overtime' => $overtime,
                'pit_brackets' => $pitBrackets,
                'insurance_base' => $this->insuranceBase($employee, $rules),
                'personal_deduction' => $personalDeduction,
                'dependent_deduction' => $dependentDeduction,
                'shortfall' => $net < 0 ? abs($net) : 0.0,
            ],
        ];
    }

    private function insuranceBase(array $employee, array $rules): float
    {
        $base = (float) ($employee['insurance_salary'] ?? 0) > 0 ? (float) $employee['insurance_salary'] : (float) $employee['base_salary'];
        $cap = $rules['insurance']['salary_cap'] ?? null;

        return $cap ? min($base, (float) $cap) : $base;
    }

    private function insurance(array $employee, array $rules, callable $round): float
    {
        return $round($this->insuranceBase($employee, $rules) * (float) ($rules['insurance']['employee_rate'] ?? 0));
    }

    /**
     * Thuế lũy tiến từng phần. Mỗi bậc có up_to (null = không giới hạn) và rate.
     *
     * @param  list<array{up_to: int|float|null, rate: float}>  $brackets
     * @return array{0: float, 1: list<array{from: float, to: ?float, rate: float, amount: float}>}
     */
    private function personalIncomeTax(float $taxable, array $brackets, callable $round): array
    {
        $tax = 0.0;
        $detail = [];
        $lower = 0.0;
        foreach ($brackets as $bracket) {
            $upper = $bracket['up_to'] === null ? null : (float) $bracket['up_to'];
            $slice = ($upper === null ? $taxable : min($taxable, $upper)) - $lower;
            if ($slice > 0) {
                $amount = $slice * (float) $bracket['rate'];
                $tax += $amount;
                $detail[] = ['from' => $lower, 'to' => $upper, 'rate' => (float) $bracket['rate'], 'amount' => $round($amount)];
            }
            if ($upper === null || $taxable <= $upper) {
                break;
            }
            $lower = $upper;
        }

        return [$round($tax), $detail];
    }

    private function round(float $amount, array $rules): float
    {
        $step = max((float) ($rules['standard']['round_to'] ?? 1), 1.0);

        return round($amount / $step) * $step;
    }
}
