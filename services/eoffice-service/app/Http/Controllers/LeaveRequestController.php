<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Models\LeaveRequest;
use App\Services\IdentityClient;
use App\Services\LeaveDayCalculator;
use App\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $service) {}

    /**
     * Mọi đơn nghỉ người đăng nhập được xem:
     * - mine: đơn của chính mình (kể cả nháp)
     * - team: đơn của nhân viên nhận mình làm line manager (trừ nháp)
     * - to_approve: đơn đang chờ mình duyệt
     * - all (mặc định): gộp cả 3 nhóm trên + đơn mình từng được gán duyệt; Super Admin / eoffice.manage thấy toàn công ty
     */
    public function index(Request $request, IdentityClient $identity): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $request->validate(['scope' => ['nullable', Rule::in(['all', 'mine', 'team', 'to_approve'])]]);
        $scope = (string) $request->input('scope', 'all');
        $teamIds = in_array($scope, ['all', 'team'], true) ? $identity->directReportIds($actor->id) : [];
        $myPendingStep = fn ($steps) => $steps->where('approver_id', $actor->id)->where('status', ApprovalStep::PENDING);

        $query = LeaveRequest::with(['leaveType', 'approvalSteps'])->orderByDesc('start_date')->orderByDesc('id');
        match ($scope) {
            'mine' => $query->where('user_id', $actor->id),
            'team' => $query->whereIn('user_id', $teamIds)->where('status', '!=', LeaveRequest::DRAFT),
            'to_approve' => $query->whereHas('approvalSteps', $myPendingStep),
            default => $query->where(function ($visible) use ($actor, $teamIds) {
                $visible->where('user_id', $actor->id)->orWhere(function ($others) use ($actor, $teamIds) {
                    $others->where('status', '!=', LeaveRequest::DRAFT);
                    if (! $this->canManage($actor)) {
                        $others->where(fn ($related) => $related->whereIn('user_id', $teamIds)
                            ->orWhereHas('approvalSteps', fn ($steps) => $steps->where('approver_id', $actor->id)));
                    }
                });
            }),
        };
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }
        if ($request->filled('year')) {
            $query->whereYear('start_date', $request->integer('year'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn ($match) => $match->where('code', 'like', "%{$search}%")->orWhere('user_name', 'like', "%{$search}%"));
        }
        $requests = $query->paginate(min(max($request->integer('per_page', 20), 1), 200));

        return response()->json([
            'success' => true, 'data' => $requests->items(), 'total' => $requests->total(),
            'pending_count' => LeaveRequest::whereHas('approvalSteps', $myPendingStep)->count(),
            'meta' => ['current_page' => $requests->currentPage(), 'last_page' => $requests->lastPage(), 'per_page' => $requests->perPage()],
        ]);
    }

    public function show(Request $request, LeaveRequest $leaveRequest, IdentityClient $identity): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $leaveRequest->load(['leaveType', 'approvalSteps', 'approvalHistories']);
        $isOwner = (int) $leaveRequest->user_id === $actor->id;
        $isApprover = $leaveRequest->approvalSteps->contains('approver_id', $actor->id);
        $visibleToOthers = $leaveRequest->status !== LeaveRequest::DRAFT
            && ($isApprover || $this->canManage($actor) || in_array((int) $leaveRequest->user_id, $identity->directReportIds($actor->id), true));
        if (! $isOwner && ! $visibleToOthers) {
            throw new AccessDeniedHttpException('Bạn không có quyền xem đơn này.');
        }

        return response()->json(['success' => true, 'data' => $leaveRequest]);
    }

    private function canManage(object $actor): bool
    {
        return in_array('eoffice.manage', $actor->permissions, true) || in_array('SUPER_ADMIN', $actor->roles, true);
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
