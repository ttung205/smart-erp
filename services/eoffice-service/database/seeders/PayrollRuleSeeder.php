<?php

namespace Database\Seeders;

use App\Models\PayrollRule;
use Illuminate\Database\Seeder;

/**
 * Giá trị mặc định ban đầu của bảng quy tắc lương. Đây là GIẢ ĐỊNH để chạy được hệ thống:
 * kế toán/HR phải đối chiếu hệ số OT, mức phạt, trần bảo hiểm, giảm trừ và biểu thuế với quy định hiện hành
 * của công ty và pháp luật rồi sửa trong payroll_rules (không cần deploy lại).
 */
class PayrollRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            'standard' => [
                ['hours_per_day' => 8, 'round_to' => 1],
                'Giờ làm chuẩn mỗi ngày (tính đơn giá giờ) và bước làm tròn tiền (đồng)',
            ],
            'ot_multipliers' => [
                ['WEEKDAY' => 1.5, 'WEEKEND' => 2.0, 'HOLIDAY' => 3.0],
                'Hệ số tiền làm thêm giờ: ngày thường / ngày nghỉ hằng tuần / ngày lễ',
            ],
            'late_penalty' => [
                ['free_count' => 2, 'amount_per_violation' => 50000],
                'Số lần đi trễ/về sớm trong tháng được miễn phạt và mức phạt mỗi lần vượt quá (đồng)',
            ],
            'insurance' => [
                ['employee_rate' => 0.105, 'salary_cap' => 46800000],
                'Bảo hiểm phần người lao động (BHXH 8% + BHYT 1,5% + BHTN 1%) và trần lương đóng bảo hiểm',
            ],
            'pit' => [
                [
                    'personal_deduction' => 11000000,
                    'dependent_deduction' => 4400000,
                    'brackets' => [
                        ['up_to' => 5000000, 'rate' => 0.05],
                        ['up_to' => 10000000, 'rate' => 0.10],
                        ['up_to' => 18000000, 'rate' => 0.15],
                        ['up_to' => 32000000, 'rate' => 0.20],
                        ['up_to' => 52000000, 'rate' => 0.25],
                        ['up_to' => 80000000, 'rate' => 0.30],
                        ['up_to' => null, 'rate' => 0.35],
                    ],
                ],
                'Giảm trừ gia cảnh và biểu thuế thu nhập cá nhân lũy tiến theo tháng',
            ],
        ];

        foreach ($rules as $key => [$value, $description]) {
            PayrollRule::firstOrCreate(['rule_key' => $key], ['value' => $value, 'description' => $description]);
        }
    }
}
