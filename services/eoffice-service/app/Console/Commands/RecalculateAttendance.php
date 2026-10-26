<?php

namespace App\Console\Commands;

use App\Models\EmployeeProfile;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RecalculateAttendance extends Command
{
    protected $signature = 'attendance:recalculate {months* : Tháng cần tính lại, dạng YYYY-MM} {--user= : Chỉ một nhân viên (user id)} {--force : Tính lại cả ngày đã khóa công}';

    protected $description = 'Tính lại bảng công theo tháng từ log chấm công, nghỉ phép và yêu cầu đã duyệt';

    public function handle(AttendanceService $attendance): int
    {
        $months = (array) $this->argument('months');
        foreach ($months as $month) {
            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                $this->error("Tháng không hợp lệ: {$month} (cần dạng YYYY-MM).");

                return self::INVALID;
            }
        }

        $profiles = EmployeeProfile::where('is_active', true)
            ->when($this->option('user'), fn ($query, $user) => $query->where('user_id', (int) $user))->get();
        foreach ($months as $month) {
            $days = 0;
            foreach ($profiles as $profile) {
                $days += $attendance->recalculateMonth($profile->user_id, $month, (bool) $this->option('force'));
            }
            $this->info(sprintf('%s: %d nhân viên, %d ngày đã tính lại.', CarbonImmutable::createFromFormat('!Y-m', $month)->format('m/Y'), $profiles->count(), $days));
        }

        return self::SUCCESS;
    }
}
