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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[OA\Schema(
    schema: 'AttendanceRequestInput',
    required: ['type', 'start_date', 'reason'],
    properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['ADD_PUNCH', 'MAKEUP', 'REMOTE', 'BUSINESS_TRIP', 'OVERTIME'], description: 'Bổ sung công, làm bù, làm từ xa, công tác, tăng ca'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date', nullable: true, description: 'Sau hoặc bằng start_date'),
        new OA\Property(property: 'requested_in', type: 'string', nullable: true, example: '08:00', description: 'Giờ vào HH:MM'),
        new OA\Property(property: 'requested_out', type: 'string', nullable: true, example: '17:30', description: 'Giờ ra HH:MM'),
        new OA\Property(property: 'reason', type: 'string', maxLength: 2000),
    ],
    type: 'object'
)]
class AttendanceRequestController extends Controller
{
    public function __construct(private readonly AttendanceRequestService $service) {}

    /** scope: mine | team | to_approve | all (mặc định: của mình + nhân viên dưới quyền + được gán duyệt; attendance.view / attendance.manage thấy tất cả). */
    #[OA\Get(
        path: '/attendance-requests',
        summary: 'Danh sách yêu cầu công',
        description: 'Người dùng thấy yêu cầu của mình, nhân viên dưới quyền (trừ nháp) và yêu cầu mình được gán duyệt. Có attendance.view hoặc attendance.manage thì thấy tất cả.',
        tags: ['Yêu cầu công'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'scope', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'all', enum: ['all', 'mine', 'team', 'to_approve'])),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ADD_PUNCH', 'MAKEUP', 'REMOTE', 'BUSINESS_TRIP', 'OVERTIME'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'RETURNED', 'CANCELLED'])),
            new OA\Parameter(name: 'month', in: 'query', required: false, description: 'Lọc theo tháng của ngày bắt đầu, dạng YYYY-MM', schema: new OA\Schema(type: 'string', example: '2026-10')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 200, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách yêu cầu, tổng số, số chờ tôi duyệt và phân trang'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
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

    #[OA\Get(
        path: '/attendance-requests/{attendanceRequest}',
        summary: 'Chi tiết yêu cầu công',
        description: 'Xem được: chủ yêu cầu; hoặc với yêu cầu không phải nháp: người duyệt, quản lý trực tiếp, người có attendance.view hoặc attendance.manage.',
        tags: ['Yêu cầu công'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'attendanceRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Yêu cầu kèm bước và lịch sử duyệt'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
        ]
    )]
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

    #[OA\Post(
        path: '/attendance-requests',
        summary: 'Tạo yêu cầu công',
        description: 'Tạo yêu cầu nháp cho chính mình. Gửi thêm submit = true để tạo và gửi duyệt ngay.',
        tags: ['Yêu cầu công'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/AttendanceRequestInput'),
                    new OA\Schema(properties: [new OA\Property(property: 'submit', type: 'boolean', default: false)], type: 'object'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Yêu cầu mới'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $attendanceRequest = $this->service->create($this->validated($request), $actor);
        if ($request->boolean('submit')) {
            $attendanceRequest = $this->service->submit($attendanceRequest, $actor);
        }

        return response()->json(['success' => true, 'data' => $attendanceRequest], 201);
    }

    #[OA\Put(
        path: '/attendance-requests/{attendanceRequest}',
        summary: 'Sửa yêu cầu công',
        description: 'Chỉ chủ yêu cầu, khi yêu cầu ở trạng thái DRAFT hoặc RETURNED.',
        tags: ['Yêu cầu công'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'attendanceRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AttendanceRequestInput')),
        responses: [
            new OA\Response(response: 200, description: 'Yêu cầu sau khi sửa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function update(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->update($attendanceRequest, $this->validated($request), $request->attributes->get('actor'))]);
    }

    #[OA\Delete(
        path: '/attendance-requests/{attendanceRequest}',
        summary: 'Xóa yêu cầu công',
        description: 'Chỉ chủ yêu cầu, khi yêu cầu còn sửa được.',
        tags: ['Yêu cầu công'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'attendanceRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function destroy(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        $this->service->delete($attendanceRequest, $request->attributes->get('actor'));

        return response()->json(['success' => true]);
    }

    #[OA\Post(
        path: '/attendance-requests/{attendanceRequest}/submit',
        summary: 'Gửi duyệt yêu cầu công',
        description: 'Chỉ chủ yêu cầu. Yêu cầu chuyển sang PENDING và sinh các bước duyệt.',
        tags: ['Yêu cầu công'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'attendanceRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Yêu cầu sau khi gửi duyệt'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function submit(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->submit($attendanceRequest, $request->attributes->get('actor'))]);
    }

    #[OA\Post(
        path: '/attendance-requests/{attendanceRequest}/cancel',
        summary: 'Hủy yêu cầu công',
        tags: ['Yêu cầu công'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'attendanceRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', nullable: true, maxLength: 1000)])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Yêu cầu sau khi hủy'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
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
