<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    public const SATURDAY_OFF = 'OFF';

    public const SATURDAY_HALF = 'HALF';

    public const SATURDAY_FULL = 'FULL';

    protected $guarded = [];

    protected $casts = [
        'grace_minutes' => 'int', 'full_day_minutes' => 'int', 'half_day_minutes' => 'int',
        'is_default' => 'boolean', 'is_active' => 'boolean',
    ];

    /** Số phút làm việc chuẩn trong ngày (không tính giờ nghỉ). */
    public function standardMinutes(): int
    {
        $total = $this->minutesOf($this->end_time) - $this->minutesOf($this->start_time);
        if ($this->break_start && $this->break_end) {
            $total -= $this->minutesOf($this->break_end) - $this->minutesOf($this->break_start);
        }

        return max($total, 0);
    }

    /** Số phút kể từ 00:00 của chuỗi giờ H:i hoặc H:i:s. */
    public function minutesOf(?string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', (string) $time) + [0, 0]);

        return $hours * 60 + $minutes;
    }
}
