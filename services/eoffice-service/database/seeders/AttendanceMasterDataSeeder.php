<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class AttendanceMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Ca hành chính mặc định: 08:00-17:00, nghỉ trưa 12:00-13:00 (8 giờ làm việc), thứ 7 nghỉ.
        // Đây là giả định ban đầu, HR chỉnh lại ở màn hình ca làm việc cho đúng nội quy công ty.
        Shift::updateOrCreate(['code' => 'HC'], [
            'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00',
            'break_start' => '12:00', 'break_end' => '13:00', 'grace_minutes' => 10,
            'full_day_minutes' => 420, 'half_day_minutes' => 240, 'saturday_mode' => Shift::SATURDAY_OFF,
            'is_default' => true, 'is_active' => true,
        ]);
    }
}
