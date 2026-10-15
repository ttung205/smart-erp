<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\IdentityClient;
use App\Services\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Danh mục nghỉ phép (loại nghỉ, ngày lễ), quỹ phép và lịch nghỉ của phòng. */
class LeaveSetupController extends Controller
{
    public function leaveTypes(Request $request): JsonResponse
    {
        $types = LeaveType::query()->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => $types]);
    }

    public function saveLeaveType(Request $request, ?LeaveType $leaveType = null): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('leave_types', 'code')->ignore($leaveType?->id)],
            'name' => ['required', 'string', 'max:100'],
            'is_paid' => ['boolean'], 'deducts_balance' => ['boolean'], 'requires_attachment' => ['boolean'], 'is_active' => ['boolean'],
            'max_days_per_request' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $data['code'] = strtoupper(trim($data['code']));
        $leaveType = $leaveType ? tap($leaveType)->update($data) : LeaveType::create($data);

        return response()->json(['success' => true, 'data' => $leaveType], $leaveType->wasRecentlyCreated ? 201 : 200);
    }

    public function holidays(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) today()->year);

        return response()->json(['success' => true, 'data' => Holiday::whereYear('date', $year)->orderBy('date')->get()]);
    }

    public function storeHoliday(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'unique:holidays,date'], 'name' => ['required', 'string', 'max:150'],
        ]);

        return response()->json(['success' => true, 'data' => Holiday::create($data)], 201);
    }

    public function destroyHoliday(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return response()->json(['success' => true]);
    }

    public function myBalance(Request $request, LeaveBalanceService $balances): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $balance = $balances->forUser($actor->id, $request->integer('year', (int) today()->year));

        return response()->json(['success' => true, 'data' => $balances->present($balance)]);
    }

    /** Bảng quỹ phép toàn công ty: mọi nhân viên đang hoạt động trong identity-service. */
    public function balances(Request $request, LeaveBalanceService $balances, IdentityClient $identity): JsonResponse
    {
        $year = $request->integer('year', (int) today()->year);
        $users = collect($identity->users())->filter(fn (array $user) => (bool) ($user['is_active'] ?? true))->keyBy('id');
        $rows = $users->keys()->map(fn ($userId) => $balances->forUser((int) $userId, $year));

        $data = collect($balances->presentMany($rows))->map(fn (array $row) => $row + [
            'user_name' => $users[$row['user_id']]['name'] ?? "#{$row['user_id']}",
            'email' => $users[$row['user_id']]['email'] ?? null,
            'department_name' => $users[$row['user_id']]['department_name'] ?? null,
        ])->sortBy('user_name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /** HR đặt ngày đầu kỳ và số phép đầu kỳ; bỏ trống số phép thì tự tính theo tỷ lệ tháng làm việc. */
    public function updateBalance(Request $request, LeaveBalanceService $balances, int $userId): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'opening_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        if ((int) substr($data['period_start'], 0, 4) !== (int) $data['year']) {
            throw ValidationException::withMessages(['period_start' => "Ngày đầu kỳ phải nằm trong năm {$data['year']}."]);
        }
        $balance = $balances->forUser($userId, (int) $data['year']);
        $balance->update([
            'period_start' => $data['period_start'],
            'opening_days' => $data['opening_days'] ?? $balances->proRataDays($data['period_start']),
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $balances->present($balance->fresh())]);
    }

    public function proRata(Request $request, LeaveBalanceService $balances): JsonResponse
    {
        $data = $request->validate(['period_start' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['success' => true, 'data' => ['opening_days' => $balances->proRataDays($data['period_start'])]]);
    }

    /** Các đơn nghỉ trong năm của một nhân viên (xem chi tiết từ bảng quỹ phép). */
    public function userRequests(Request $request, int $userId): JsonResponse
    {
        $year = $request->integer('year', (int) today()->year);
        $requests = LeaveRequest::with('leaveType')->where('user_id', $userId)->whereYear('start_date', $year)
            ->whereNotIn('status', [LeaveRequest::DRAFT])
            ->orderByDesc('start_date')->get();

        return response()->json(['success' => true, 'data' => $requests]);
    }

    /**
     * Lịch nghỉ trong tháng (đơn chờ duyệt và đã duyệt).
     * Nhân viên thường: chỉ phòng ban của mình. Super Admin / eoffice.manage: toàn công ty, lọc được theo department_id.
     */
    public function calendar(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'department_id' => ['nullable', 'integer']]);
        $month = CarbonImmutable::createFromFormat('Y-m-d', ($request->input('month') ?? today()->format('Y-m')).'-01');
        $canViewAll = in_array('eoffice.manage', $actor->permissions, true) || in_array('SUPER_ADMIN', $actor->roles, true);
        $departmentId = $canViewAll ? ($request->integer('department_id') ?: null) : $actor->department_id;

        $requests = LeaveRequest::with('leaveType')
            ->when($departmentId || ! $canViewAll, fn ($query) => $query->where('department_id', $departmentId))
            ->whereIn('status', LeaveRequest::ACTIVE_STATUSES)
            ->whereDate('start_date', '<=', $month->endOfMonth()->toDateString())
            ->whereDate('end_date', '>=', $month->toDateString())
            ->orderBy('start_date')
            ->get(['id', 'code', 'user_id', 'user_name', 'department_id', 'leave_type_id', 'start_date', 'start_session', 'end_date', 'end_session', 'total_days', 'status']);

        return response()->json(['success' => true, 'data' => $requests, 'can_view_all' => $canViewAll]);
    }
}
