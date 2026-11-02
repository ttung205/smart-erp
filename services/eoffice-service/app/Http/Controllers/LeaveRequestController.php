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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[OA\Schema(
    schema: 'LeaveRequestInput',
    required: ['leave_type_id', 'start_date', 'end_date', 'reason'],
    properties: [
        new OA\Property(property: 'leave_type_id', type: 'integer'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
        new OA\Property(property: 'start_session', type: 'string', default: 'FULL', enum: ['FULL', 'MORNING', 'AFTERNOON']),
        new OA\Property(property: 'end_date', type: 'string', format: 'date', description: 'Sau hoặc bằng start_date'),
        new OA\Property(property: 'end_session', type: 'string', default: 'FULL', enum: ['FULL', 'MORNING', 'AFTERNOON']),
        new OA\Property(property: 'reason', type: 'string', maxLength: 2000),
        new OA\Property(property: 'handover_user_id', type: 'integer', nullable: true),
        new OA\Property(property: 'handover_user_name', type: 'string', nullable: true, maxLength: 150),
        new OA\Property(property: 'handover_note', type: 'string', nullable: true, maxLength: 2000),
    ],
    type: 'object'
)]
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
    #[OA\Get(
        path: '/leave-requests',
        summary: 'Danh sách đơn nghỉ phép',
        description: 'Người dùng chỉ thấy đơn của mình, nhân viên dưới quyền (trừ nháp) và đơn mình được gán duyệt. SUPER_ADMIN hoặc eoffice.manage thấy toàn công ty.',
        tags: ['Nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'scope', in: 'query', required: false, description: 'mine: của tôi; team: nhân viên dưới quyền; to_approve: chờ tôi duyệt; all: gộp các nhóm', schema: new OA\Schema(type: 'string', default: 'all', enum: ['all', 'mine', 'team', 'to_approve'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'RETURNED', 'CANCELLED'])),
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Tìm theo mã đơn hoặc tên nhân viên', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 200, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách đơn, tổng số, số đơn chờ tôi duyệt và phân trang'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
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

    #[OA\Get(
        path: '/leave-requests/{leaveRequest}',
        summary: 'Chi tiết đơn nghỉ phép',
        description: 'Xem được: chủ đơn; hoặc với đơn không phải nháp: người duyệt, quản lý trực tiếp, SUPER_ADMIN, eoffice.manage. Kèm các bước và lịch sử duyệt.',
        tags: ['Nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'leaveRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đơn nghỉ kèm loại nghỉ, bước duyệt và lịch sử'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
        ]
    )]
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

    #[OA\Post(
        path: '/leave-requests',
        summary: 'Tạo đơn nghỉ phép',
        description: 'Tạo đơn nháp cho chính mình. Gửi thêm submit = true để tạo và gửi duyệt ngay.',
        tags: ['Nghỉ phép'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/LeaveRequestInput'),
                    new OA\Schema(properties: [new OA\Property(property: 'submit', type: 'boolean', default: false)], type: 'object'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Đơn nghỉ mới'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $leaveRequest = $this->service->create($this->validated($request), $request->attributes->get('actor'));
        if ($request->boolean('submit')) {
            $leaveRequest = $this->service->submit($leaveRequest, $request->attributes->get('actor'));
        }

        return response()->json(['success' => true, 'data' => $leaveRequest->load('leaveType')], 201);
    }

    #[OA\Put(
        path: '/leave-requests/{leaveRequest}',
        summary: 'Sửa đơn nghỉ phép',
        description: 'Chỉ chủ đơn, khi đơn ở trạng thái DRAFT hoặc RETURNED.',
        tags: ['Nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'leaveRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LeaveRequestInput')),
        responses: [
            new OA\Response(response: 200, description: 'Đơn sau khi sửa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function update(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest = $this->service->update($leaveRequest, $this->validated($request), $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $leaveRequest->load('leaveType')]);
    }

    #[OA\Delete(
        path: '/leave-requests/{leaveRequest}',
        summary: 'Xóa đơn nghỉ phép',
        description: 'Chỉ chủ đơn, khi đơn còn sửa được.',
        tags: ['Nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'leaveRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function destroy(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->service->delete($leaveRequest, $request->attributes->get('actor'));

        return response()->json(['success' => true]);
    }

    #[OA\Post(
        path: '/leave-requests/{leaveRequest}/submit',
        summary: 'Gửi duyệt đơn nghỉ phép',
        description: 'Chỉ chủ đơn. Đơn chuyển sang PENDING và sinh các bước duyệt.',
        tags: ['Nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'leaveRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đơn sau khi gửi duyệt'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function submit(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->submit($leaveRequest, $request->attributes->get('actor'))]);
    }

    #[OA\Post(
        path: '/leave-requests/{leaveRequest}/cancel',
        summary: 'Hủy đơn nghỉ phép',
        tags: ['Nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'leaveRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', nullable: true, maxLength: 1000)])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Đơn sau khi hủy'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return response()->json(['success' => true, 'data' => $this->service->cancel($leaveRequest, $request->attributes->get('actor'), $data['reason'] ?? null)]);
    }

    /** Tính thử số ngày nghỉ để hiển thị trên form trước khi lưu. */
    #[OA\Post(
        path: '/leave-requests/preview',
        summary: 'Tính thử số ngày nghỉ',
        description: 'Tính số ngày nghỉ (trừ cuối tuần, ngày lễ, theo buổi) để hiển thị trên form trước khi lưu.',
        tags: ['Nghỉ phép'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['start_date', 'end_date'],
                properties: [
                    new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'start_session', type: 'string', default: 'FULL', enum: ['FULL', 'MORNING', 'AFTERNOON']),
                    new OA\Property(property: 'end_session', type: 'string', default: 'FULL', enum: ['FULL', 'MORNING', 'AFTERNOON']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'total_days'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
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
