<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quỹ phép năm theo kỳ: HR nhập ngày đầu kỳ + số phép đầu kỳ.
 * "Đã nghỉ" và "chờ duyệt" luôn tính trực tiếp từ đơn phép năm có ngày bắt đầu >= ngày đầu kỳ,
 * nên sửa đầu kỳ hay hủy đơn đều không cần cập nhật bộ đếm.
 */
class LeaveBalanceService
{
    public function forUser(int $userId, int $year): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            ['user_id' => $userId, 'year' => $year],
            ['period_start' => "{$year}-01-01", 'opening_days' => (float) config('eoffice.annual_leave_days', 12)],
        );
    }

    /**
     * Phép năm theo tỷ lệ tháng làm việc (Điều 113 BLLĐ 2019, Điều 66 NĐ 145/2020):
     * số phép năm / 12 × số tháng còn lại tính từ ngày vào làm; vào làm từ ngày 15 trở về trước thì tính cả tháng đó.
     */
    public function proRataDays(string $periodStart): float
    {
        $start = CarbonImmutable::parse($periodStart);
        $months = 12 - $start->month + ($start->day <= 15 ? 1 : 0);

        return round((float) config('eoffice.annual_leave_days', 12) / 12 * $months * 2) / 2;
    }

    /** @return array<string, mixed> */
    public function present(LeaveBalance $balance): array
    {
        $usage = $this->usage(collect([$balance]))->get($balance->user_id, ['used' => 0.0, 'pending' => 0.0]);

        return $this->row($balance, $usage);
    }

    /**
     * @param  Collection<int, LeaveBalance>  $balances
     * @return list<array<string, mixed>>
     */
    public function presentMany(Collection $balances): array
    {
        $usage = $this->usage($balances);

        return $balances->map(fn (LeaveBalance $balance) => $this->row($balance, $usage->get($balance->user_id, ['used' => 0.0, 'pending' => 0.0])))->values()->all();
    }

    /** Kiểm tra đủ phép trước khi gửi duyệt (đơn đang xét không tính vào phần đã dùng). */
    public function ensureAvailable(LeaveRequest $request): void
    {
        if (! $request->leaveType?->deducts_balance) {
            return;
        }
        $balance = $this->forUser($request->user_id, $request->start_date->year);
        LeaveBalance::whereKey($balance->id)->lockForUpdate()->first();

        if ($request->start_date->lt($balance->period_start)) {
            throw ValidationException::withMessages([
                'start_date' => 'Ngày nghỉ phép năm phải từ ngày đầu kỳ '.$balance->period_start->format('d/m/Y').' trở đi.',
            ]);
        }

        $taken = (float) $this->deductingRequests($balance->user_id, $balance->year)
            ->whereIn('leave_requests.status', LeaveRequest::ACTIVE_STATUSES)
            ->whereDate('leave_requests.start_date', '>=', $balance->period_start)
            ->where('leave_requests.id', '!=', $request->id)
            ->sum('leave_requests.total_days');
        $remaining = round($balance->opening_days - $taken, 1);
        if ($remaining < $request->total_days) {
            throw ValidationException::withMessages([
                'total_days' => "Quỹ phép năm {$balance->year} chỉ còn {$remaining} ngày, không đủ cho {$request->total_days} ngày.",
            ]);
        }
    }

    /**
     * @param  Collection<int, LeaveBalance>  $balances
     * @return Collection<int, array{used: float, pending: float}>
     */
    private function usage(Collection $balances): Collection
    {
        if ($balances->isEmpty()) {
            return collect();
        }
        $periodStarts = $balances->mapWithKeys(fn (LeaveBalance $balance) => [$balance->user_id => $balance->period_start->toDateString()]);
        $rows = $this->deductingRequests($balances->pluck('user_id')->all(), (int) $balances->first()->year)
            ->whereIn('leave_requests.status', LeaveRequest::ACTIVE_STATUSES)
            ->get(['leave_requests.user_id', 'leave_requests.status', 'leave_requests.start_date', 'leave_requests.total_days']);

        return $rows
            ->filter(fn ($row) => substr((string) $row->start_date, 0, 10) >= $periodStarts[$row->user_id])
            ->groupBy('user_id')
            ->map(fn ($items) => [
                'used' => (float) $items->where('status', LeaveRequest::APPROVED)->sum('total_days'),
                'pending' => (float) $items->where('status', LeaveRequest::PENDING)->sum('total_days'),
            ]);
    }

    /** @param  int|list<int>  $userIds */
    private function deductingRequests(int|array $userIds, int $year)
    {
        return DB::table('leave_requests')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_types.deducts_balance', true)
            ->whereIn('leave_requests.user_id', (array) $userIds)
            ->whereYear('leave_requests.start_date', $year);
    }

    private function row(LeaveBalance $balance, array $usage): array
    {
        return [
            'id' => $balance->id,
            'user_id' => $balance->user_id,
            'year' => $balance->year,
            'period_start' => $balance->period_start->toDateString(),
            'opening_days' => $balance->opening_days,
            'used_days' => $usage['used'],
            'pending_days' => $usage['pending'],
            'remaining_days' => round($balance->opening_days - $usage['used'] - $usage['pending'], 1),
            'note' => $balance->note,
        ];
    }
}
