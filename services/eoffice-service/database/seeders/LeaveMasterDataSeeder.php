<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Theo Bộ luật Lao động 2019 (Điều 113, 115)
        $types = [
            ['code' => 'ANNUAL', 'name' => 'Nghỉ phép năm', 'is_paid' => true, 'deducts_balance' => true, 'max_days_per_request' => null],
            ['code' => 'SICK', 'name' => 'Nghỉ ốm', 'is_paid' => true, 'deducts_balance' => false, 'max_days_per_request' => null],
            ['code' => 'WEDDING', 'name' => 'Nghỉ kết hôn', 'is_paid' => true, 'deducts_balance' => false, 'max_days_per_request' => 3],
            ['code' => 'CHILD_WEDDING', 'name' => 'Con kết hôn', 'is_paid' => true, 'deducts_balance' => false, 'max_days_per_request' => 1],
            ['code' => 'FUNERAL', 'name' => 'Nghỉ tang (cha mẹ, vợ/chồng, con)', 'is_paid' => true, 'deducts_balance' => false, 'max_days_per_request' => 3],
            ['code' => 'UNPAID', 'name' => 'Nghỉ không lương', 'is_paid' => false, 'deducts_balance' => false, 'max_days_per_request' => null],
        ];
        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type + ['is_active' => true, 'requires_attachment' => false]);
        }

        $holidays = [
            ['date' => '2026-01-01', 'name' => 'Tết Dương lịch'],
            ['date' => '2026-02-16', 'name' => 'Tết Nguyên đán'],
            ['date' => '2026-02-17', 'name' => 'Tết Nguyên đán'],
            ['date' => '2026-02-18', 'name' => 'Tết Nguyên đán'],
            ['date' => '2026-02-19', 'name' => 'Tết Nguyên đán'],
            ['date' => '2026-02-20', 'name' => 'Tết Nguyên đán'],
            ['date' => '2026-04-27', 'name' => 'Giỗ Tổ Hùng Vương (nghỉ bù)'],
            ['date' => '2026-04-30', 'name' => 'Ngày Giải phóng miền Nam'],
            ['date' => '2026-05-01', 'name' => 'Quốc tế Lao động'],
            ['date' => '2026-09-01', 'name' => 'Quốc khánh'],
            ['date' => '2026-09-02', 'name' => 'Quốc khánh'],
        ];
        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(['date' => $holiday['date']], $holiday);
        }
    }
}
