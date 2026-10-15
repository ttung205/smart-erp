<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\LeaveDayCalculator;
use App\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $service) {}

    /** Đơn nghỉ của chính người đăng nhập. */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $query = LeaveRequest::with(['leaveType', 'approvalSteps'])->where('user_id', $actor->id)
            ->orderByDesc('start_date')->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }
        if ($request->filled('year')) {
            $query->whereYear('start_date', $request->integer('year'));
        }
        $requests = $query->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'success' => true, 'data' => $requests->items(), 'total' => $requests->total(),
            'meta' => ['current_page' => $requests->currentPage(), 'last_page' => $requests->lastPage(), 'per_page' => $requests->perPage()],
        ]);
    }

    public function show(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $leaveRequest->load(['leaveType', 'approvalSteps', 'approvalHistories']);
        $isApprover = $leaveRequest->approvalSteps->contains('approver_id', $actor->id);
        $canManage = in_array('eoffice.manage', $actor->permissions, true) || in_array('SUPER_ADMIN', $actor->roles, true);
        if ((int) $leaveRequest->user_id !== $actor->id && ! $isApprover && ! $canManage) {
            throw new AccessDeniedHttpException('Bạn không có quyền xem đơn này.');
        }

        return response()->json(['success' => true, 'data' => $leaveRequest]);
    }

    public function store(Request $request): JsonResponse
    {
        $leaveRequest = $this->service->create($this->validated($request), $request->attributes->get('actor'));
        if ($request->boolean('submit')) {
            $leaveRequest = $this->service->submit($leaveRequest, $request->attributes->get('actor'));
        }

        return response()->json(['success' => true, 'data' => $leaveRequest->load('leaveType')], 201);
    }

    public function update(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest = $this->service->update($leaveRequest, $this->validated($request), $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $leaveRequest->load('leaveType')]);
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->service->delete($leaveRequest, $request->attributes->get('actor'));

        return response()->json(['success' => true]);
    }

    public function submit(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->submit($leaveRequest, $request->attributes->get('actor'))]);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return response()->json(['success' => true, 'data' => $this->service->cancel($leaveRequest, $request->attributes->get('actor'), $data['reason'] ?? null)]);
    }

    /** Tính thử số ngày nghỉ để hiển thị trên form trước khi lưu. */
    public function preview(Request $request, LeaveDayCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d'],
            'start_session' => ['nullable', Rule::in(LeaveDayCalculator::SESSIONS)],
            'end_session' => ['nullable', Rule::in(LeaveDayCalculator::SESSIONS)],
        ]);
        $startSession = $data['start_session'] ?? LeaveDayCalculator::FULL;
        $endSession = $data['start_date'] === $data['end_date'] ? $startSession : ($data['end_session'] ?? LeaveDayCalculator::FULL);

        return response()->json(['success' => true, 'data' => [
            'total_days' => $calculator->totalDays($data['start_date'], $startSession, $data['end_date'], $endSession),
        ]]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_session' => ['nullable', Rule::in(LeaveDayCalculator::SESSIONS)],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'end_session' => ['nullable', Rule::in(LeaveDayCalculator::SESSIONS)],
            'reason' => ['required', 'string', 'max:2000'],
            'handover_user_id' => ['nullable', 'integer'],
            'handover_user_name' => ['nullable', 'string', 'max:150'],
            'handover_note' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
