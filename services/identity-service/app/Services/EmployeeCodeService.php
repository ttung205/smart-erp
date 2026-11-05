<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Sinh mã nhân viên dạng {PREFIX}-{4 số ngẫu nhiên}, ví dụ AC-2005. */
class EmployeeCodeService
{
    private const MAX_ATTEMPTS = 50;

    public function prefix(): ?string
    {
        $prefix = DB::table('company_profiles')->where('id', 1)->value('employee_code_prefix');

        return $prefix ? strtoupper((string) $prefix) : null;
    }

    /** Gán mã cho một nhân viên chưa có mã. Trả về mã đã gán, hoặc null nếu công ty chưa cấu hình tiền tố. */
    public function assign(int $userId): ?string
    {
        $prefix = $this->prefix();
        if ($prefix === null) {
            return null;
        }

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = sprintf('%s-%04d', $prefix, random_int(0, 9999));
            try {
                // Điều kiện employee_code IS NULL giữ cho việc gán idempotent; UNIQUE chặn trùng mã khi chạy đồng thời.
                $updated = DB::table('users')
                    ->where('id', $userId)
                    ->whereNull('employee_code')
                    ->update(['employee_code' => $code]);
            } catch (QueryException $exception) {
                if ($this->isDuplicate($exception)) {
                    continue;
                }
                throw $exception;
            }

            return $updated === 1 ? $code : DB::table('users')->where('id', $userId)->value('employee_code');
        }

        throw new RuntimeException('Không sinh được mã nhân viên duy nhất sau '.self::MAX_ATTEMPTS.' lần thử.');
    }

    /** Gán mã cho mọi nhân viên còn thiếu. Trả về số nhân viên vừa được gán. */
    public function assignMissing(): int
    {
        if ($this->prefix() === null) {
            return 0;
        }

        $assigned = 0;
        foreach (DB::table('users')->whereNull('employee_code')->orderBy('id')->pluck('id') as $userId) {
            if ($this->assign((int) $userId) !== null) {
                $assigned++;
            }
        }

        return $assigned;
    }

    /**
     * Đổi tiền tố cho mọi mã đã cấp, giữ nguyên 4 số (AC-2005 → SE-2005).
     * Số trùng nhau giữa hai nhân viên (chỉ xảy ra với dữ liệu cũ có nhiều tiền tố) được sinh lại cho người sau.
     */
    public function applyPrefix(string $prefix): void
    {
        DB::transaction(function () use ($prefix): void {
            $users = DB::table('users')->whereNotNull('employee_code')->orderBy('id')->lockForUpdate()->get(['id', 'employee_code']);
            $taken = [];
            foreach ($users as $user) {
                $taken[substr((string) $user->employee_code, -4)] = true;
            }

            $seen = [];
            foreach ($users as $user) {
                $digits = substr((string) $user->employee_code, -4);
                if (! ctype_digit($digits) || isset($seen[$digits])) {
                    do {
                        $digits = sprintf('%04d', random_int(0, 9999));
                    } while (isset($taken[$digits]) || isset($seen[$digits]));
                    $taken[$digits] = true;
                }
                $seen[$digits] = true;

                $newCode = "{$prefix}-{$digits}";
                if ($newCode !== $user->employee_code) {
                    DB::table('users')->where('id', $user->id)->update(['employee_code' => $newCode]);
                }
            }
        });
    }

    private function isDuplicate(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000' || ($exception->errorInfo[1] ?? null) === 1062;
    }
}
