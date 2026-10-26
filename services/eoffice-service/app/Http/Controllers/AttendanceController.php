<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AttendanceImportBatch;
use App\Models\EmployeeProfile;
use App\Models\PunchLog;
use App\Services\AttendanceService;
use App\Services\IdentityClient;
use App\Services\PunchIngestService;
use App\Support\ActorPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly PunchIngestService $ingest,
    ) {}

    /** Bảng công của chính mình trong tháng, kèm trạng thái cho phép chấm công trên web. */
    public function me(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $month = $this->month($request);
        $this->ensureMonthCalculated($actor->id, $month);

        $profile = EmployeeProfile::where('user_id', $actor->id)->first();

        return response()->json(['success' => true, 'data' => [
            'month' => $month,
            'days' => $this->daysOf($actor->id, $month),
            'summary' => $this->summaryOf($actor->id, $month),
            'work_mode' => $profile?->work_mode,
            'can_check_in_web' => (bool) $profile?->canCheckInOnWeb(),
            'today_punches' => PunchLog::where('user_id', $actor->id)->whereDate('work_date', today()->toDateString())->whereNull('voided_at')
                ->orderBy('punched_at')->pluck('punched_at'),
        ]]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $day = $this->ingest->checkInOnWeb($request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $day], 201);
    }

    /** Chi tiết công theo ngày của một nhân viên: chính mình, nhân viên dưới quyền, hoặc bất kỳ ai nếu có quyền xem công. */
    public function days(Request $request, IdentityClient $identity): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $month = $this->month($request);
        $userId = $request->integer('user_id', $actor->id);
        if ($userId !== $actor->id && ! ActorPermissions::has($actor, 'attendance.view', 'attendance.manage')
            && ! in_array($userId, $identity->directReportIds($actor->id), true)) {
            throw new AccessDeniedHttpException('Bạn không có quyền xem bảng công của nhân viên này.');
        }
        $this->ensureMonthCalculated($userId, $month);

        return response()->json(['success' => true, 'data' => [
            'month' => $month, 'days' => $this->daysOf($userId, $month), 'summary' => $this->summaryOf($userId, $month),
        ]]);
    }

    /** Tổng hợp công cả công ty trong tháng (cần attendance.view). */
    public function summary(Request $request): JsonResponse
    {
        $month = $this->month($request);
        [$start, $end] = $this->monthRange($month);

        $rows = AttendanceDay::query()
            ->whereBetween('work_date', [$start, $end])
            ->groupBy('user_id')
            ->select([
                'user_id',
                DB::raw('SUM(work_value) as work_days'),
                DB::raw('SUM(CASE WHEN late_minutes > 0 THEN 1 ELSE 0 END) as late_count'),
                DB::raw('SUM(late_minutes) as late_minutes'),
                DB::raw("SUM(CASE WHEN status = 'ABSENT' THEN 1 ELSE 0 END) as absent_days"),
                DB::raw("SUM(CASE WHEN status = 'MISSING_PUNCH' THEN 1 ELSE 0 END) as missing_punch_days"),
                DB::raw("SUM(CASE WHEN status = 'LEAVE_PAID' THEN 1 ELSE 0 END) as leave_paid_days"),
                DB::raw("SUM(CASE WHEN status = 'LEAVE_UNPAID' THEN 1 ELSE 0 END) as leave_unpaid_days"),
            ])->get();

        $profiles = EmployeeProfile::whereIn('user_id', $rows->pluck('user_id'))->get()->keyBy('user_id');

        return response()->json(['success' => true, 'data' => $rows->map(fn ($row) => [
            'user_id' => (int) $row->user_id,
            'user_name' => $profiles[$row->user_id]->user_name ?? null,
            'employee_code' => $profiles[$row->user_id]->employee_code ?? null,
            'work_days' => (float) $row->work_days,
            'late_count' => (int) $row->late_count,
            'late_minutes' => (int) $row->late_minutes,
            'absent_days' => (int) $row->absent_days,
            'missing_punch_days' => (int) $row->missing_punch_days,
            'leave_paid_days' => (int) $row->leave_paid_days,
            'leave_unpaid_days' => (int) $row->leave_unpaid_days,
        ])->values()]);
    }

    /** Nhập log từ file máy chấm công (client đã đọc Excel/CSV thành các dòng). */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file_name' => ['nullable', 'string', 'max:255'],
            'rows' => ['required', 'array', 'min:1', 'max:'.PunchIngestService::MAX_ROWS],
            'rows.*' => ['array'],
            'rows.*.employee_code' => ['nullable'],
            'rows.*.punched_at' => ['nullable'],
            'rows.*.device_id' => ['nullable'],
        ]);

        $batch = $this->ingest->importRows($data['rows'], PunchLog::DEVICE_IMPORT, $request->attributes->get('actor'), $data['file_name'] ?? null);

        return response()->json(['success' => true, 'data' => $batch], 201);
    }

    public function batches(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => AttendanceImportBatch::orderByDesc('id')->limit(50)->get()]);
    }

    /** Tính lại công của một nhân viên hoặc toàn công ty (không động tới ngày đã khóa trừ khi force). */
    public function recalculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'], 'user_id' => ['nullable', 'integer'], 'force' => ['nullable', 'boolean'],
        ]);
        $userIds = isset($data['user_id']) ? [(int) $data['user_id']] : EmployeeProfile::where('is_active', true)->pluck('user_id')->all();
        foreach ($userIds as $userId) {
            $this->attendance->recalculateMonth($userId, $data['month'], (bool) ($data['force'] ?? false));
        }

        return response()->json(['success' => true, 'data' => ['users' => count($userIds), 'month' => $data['month']]]);
    }

    private function month(Request $request): string
    {
        return $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? CarbonImmutable::today()->format('Y-m');
    }

    /** @return array{0: string, 1: string} */
    private function monthRange(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);

        return [$start->toDateString(), $start->endOfMonth()->toDateString()];
    }

    private function ensureMonthCalculated(int $userId, string $month): void
    {
        [$start, $end] = $this->monthRange($month);
        $expected = (int) CarbonImmutable::parse($start)->diffInDays(min(CarbonImmutable::parse($end), CarbonImmutable::today())->addDay(), false);
        if ($expected > 0 && AttendanceDay::where('user_id', $userId)->whereBetween('work_date', [$start, $end])->count() < $expected) {
            $this->attendance->recalculateMonth($userId, $month);
        }
    }

    private function daysOf(int $userId, string $month)
    {
        [$start, $end] = $this->monthRange($month);

        return AttendanceDay::where('user_id', $userId)->whereBetween('work_date', [$start, $end])->orderBy('work_date')->get();
    }

    /** @return array<string, int|float> */
    private function summaryOf(int $userId, string $month): array
    {
        $days = $this->daysOf($userId, $month);

        return [
            'work_days' => (float) $days->sum('work_value'),
            'late_count' => $days->where('late_minutes', '>', 0)->count(),
            'late_minutes' => (int) $days->sum('late_minutes'),
            'absent_days' => $days->where('status', AttendanceDay::ABSENT)->count(),
            'missing_punch_days' => $days->where('status', AttendanceDay::MISSING_PUNCH)->count(),
            'leave_paid_days' => $days->where('status', AttendanceDay::LEAVE_PAID)->count(),
            'leave_unpaid_days' => $days->where('status', AttendanceDay::LEAVE_UNPAID)->count(),
        ];
    }
}
