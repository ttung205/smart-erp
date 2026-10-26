<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    public const ONSITE = 'ONSITE';

    public const REMOTE = 'REMOTE';

    public const HYBRID = 'HYBRID';

    public const WORK_MODES = [self::ONSITE, self::REMOTE, self::HYBRID];

    protected $guarded = [];

    /** Lương không lộ ra response mặc định; chỉ API quản trị chủ động thêm vào. */
    protected $hidden = ['base_salary', 'insurance_salary', 'fixed_allowance', 'bank_name', 'bank_account'];

    protected $casts = [
        'hire_date' => 'date:Y-m-d', 'termination_date' => 'date:Y-m-d',
        'base_salary' => 'float', 'insurance_salary' => 'float', 'fixed_allowance' => 'float', 'dependents' => 'int', 'is_active' => 'boolean',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** Có được chấm công trên web không (làm việc từ xa). */
    public function canCheckInOnWeb(): bool
    {
        return in_array($this->work_mode, [self::REMOTE, self::HYBRID], true);
    }
}
