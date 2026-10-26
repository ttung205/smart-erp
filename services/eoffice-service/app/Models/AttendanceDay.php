<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDay extends Model
{
    public const PRESENT = 'PRESENT';

    public const HALF_DAY = 'HALF_DAY';

    /** Chỉ có một lần chấm trong ngày đã qua: chưa tính công cho đến khi bổ sung. */
    public const MISSING_PUNCH = 'MISSING_PUNCH';

    public const ABSENT = 'ABSENT';

    public const LEAVE_PAID = 'LEAVE_PAID';

    public const LEAVE_UNPAID = 'LEAVE_UNPAID';

    public const HOLIDAY = 'HOLIDAY';

    public const WEEKEND = 'WEEKEND';

    /** Làm việc từ xa đã được duyệt: tính 1 công, không cần log chấm công. */
    public const REMOTE = 'REMOTE';

    public const BUSINESS_TRIP = 'BUSINESS_TRIP';

    /** Ngày chưa kết thúc (hôm nay chưa đủ dữ liệu). */
    public const PENDING = 'PENDING';

    /** Trước ngày vào làm hoặc sau ngày nghỉ việc. */
    public const OUT_OF_EMPLOYMENT = 'OUT_OF_EMPLOYMENT';

    protected $guarded = [];

    protected $casts = [
        'work_date' => 'date:Y-m-d', 'first_in' => 'datetime', 'last_out' => 'datetime',
        'work_value' => 'float', 'locked_at' => 'datetime', 'calculated_at' => 'datetime',
    ];

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
