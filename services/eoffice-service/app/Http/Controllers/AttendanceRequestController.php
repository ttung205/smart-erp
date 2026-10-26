<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Models\AttendanceRequest;
use App\Services\AttendanceRequestService;
use App\Services\IdentityClient;
use App\Support\ActorPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AttendanceRequestController extends Controller
{
    public function __construct(private readonly AttendanceRequestService $service) {}

    /** scope: mine | team | to_approve | all (mặc định: của mình + nhân viên dưới quyền + được gán duyệt; attendance.view / attendance.manage thấy tất cả). */
    public function index(Request $request, IdentityClient $identity): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $request->validate(['scope' => ['nullable', Rule::in(['all', 'mine', 'team', 'to_approve'])], 'type' => ['nullable', Rule::in(AttendanceRequest::TYPES)]]);
        $scope = (string) $request->input('scope', 'all');
        $teamIds = in_array($scope, ['all', 'team'], true) ? $identity->directReportIds($actor->id) : [];
        $myPendingStep = fn ($steps) => $steps->where('approver_id', $actor->id)->where('status', ApprovalStep::PENDING);

        $query = AttendanceRequest::with('approvalSteps')->orderByDesc('start_date')->orderByDesc('id');
        match ($scope) {
            'mine' => $query->where('user_id', $actor->id),
            'team' => $query->whereIn('user_id', $teamIds)->where('status', '!=', AttendanceRequest::DRAFT),
            'to_approve' => $query->whereHas('approvalSteps', $myPendingStep),
            default => $query->where(function ($visible) use ($actor, $teamIds) {
                $visible->where('user_id', $actor->id)->orWhere(function ($others) use ($actor, $teamIds) {
                    $others->where('status', '!=', AttendanceRequest::DRAFT);
                    if (! ActorPermissions::has($actor, 'attendance.view', 'attendance.manage')) {
                        $others->where(fn ($related) => $related->whereIn('user_id', $teamIds)
                            ->orWhereHas('approvalSteps', fn ($steps) => $steps->where('approver_id', $actor->id)));
                    }
                });
            }),
        };
        foreach (['status', 'type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->input($filter));
            }
        }
        if ($request->filled('month')) {
            $start = CarbonImmutable::createFromFormat('!Y-m', (string) $request->input('month'));
            $query->whereBetween('start_date', [$start->toDateString(), $start->endOfMonth()->toDateString()]);
        }
        $requests = $query->paginate(min(max($request->integer('per_page', 20), 1), 200));

        return response()->json([
            'success' => true, 'data' => $requests->items(), 'total' => $requests->total(),
            'pending_count' => AttendanceRequest::whereHas('approvalSteps', $myPendingStep)->count(),
            'meta' => ['current_page' => $requests->currentPage(), 'last_page' => $requests->lastPage(), 'per_page' => $requests->perPage()],
        ]);
    }

    public function show(Request $request, AttendanceRequest $attendanceRequest, IdentityClient $identity): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $attendanceRequest->load(['approvalSteps', 'approvalHistories']);
        $isOwner = (int) $attendanceRequest->user_id === $actor->id;
        $isApprover = $attendanceRequest->approvalSteps->contains('approver_id', $actor->id);
        $visibleToOthers = $attendanceRequest->status !== AttendanceRequest::DRAFT
            && ($isApprover || ActorPermissions::has($actor, 'attendance.view', 'attendance.manage')
                || in_array((int) $attendanceRequest->user_id, $identity->directReportIds($actor->id), true));
        if (! $isOwner && ! $visibleToOthers) {
            throw new AccessDeniedHttpException('Bạn không có quyền xem yêu cầu này.');
        }

        return response()->json(['success' => true, 'data' => $attendanceRequest]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $attendanceRequest = $this->service->create($this->validated($request), $actor);
        if ($request->boolean('submit')) {
            $attendanceRequest = $this->service->submit($attendanceRequest, $actor);
        }

        return response()->json(['success' => true, 'data' => $attendanceRequest], 201);
    }

    public function update(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->update($attendanceRequest, $this->validated($request), $request->attributes->get('actor'))]);
    }

    public function destroy(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        $this->service->delete($attendanceRequest, $request->attributes->get('actor'));

        return response()->json(['success' => true]);
    }

    public function submit(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->submit($attendanceRequest, $request->attributes->get('actor'))]);
    }

    public function cancel(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return response()->json(['success' => true, 'data' => $this->service->cancel($attendanceRequest, $request->attributes->get('actor'), $data['reason'] ?? null)]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(AttendanceRequest::TYPES)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'requested_in' => ['nullable', 'date_format:H:i'],
            'requested_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
    }
}
