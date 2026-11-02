<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAuditLog;
use App\Models\PunchLog;
use App\Services\AttendanceAdjustmentService;
use App\Services\IdentityClient;
use App\Support\ActorPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** HR thêm / xóa công thủ công và tra cứu log chấm công, nhật ký thay đổi. */
class AttendanceAdjustmentController extends Controller
{
    public function __construct(private readonly AttendanceAdjustmentService $adjustments) {}

    #[OA\Post(
        path: '/attendance/manual-work',
        summary: 'Thêm công thủ công',
        description: 'Yêu cầu quyền attendance.manage. Ghi nhật ký điều chỉnh kèm lý do.',
        tags: ['Điều chỉnh công'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'date', 'reason'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                    new OA\Property(property: 'in', type: 'string', nullable: true, example: '08:00', description: 'Giờ vào HH:MM'),
                    new OA\Property(property: 'out', type: 'string', nullable: true, example: '17:30', description: 'Giờ ra HH:MM'),
                    new OA\Property(property: 'reason', type: 'string', maxLength: 500, minLength: 5),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ngày công sau điều chỉnh'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền attendance.manage'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function addWork(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d'],
            'in' => ['nullable', 'date_format:H:i'], 'out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $day = $this->adjustments->addWork($data['user_id'], $data['date'], $data['in'] ?? null, $data['out'] ?? null, $data['reason'], $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $day], 201);
    }

    #[OA\Post(
        path: '/attendance/clear-day',
        summary: 'Xóa công của một ngày',
        description: 'Yêu cầu quyền attendance.manage. Ghi nhật ký điều chỉnh kèm lý do.',
        tags: ['Điều chỉnh công'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'date', 'reason'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                    new OA\Property(property: 'reason', type: 'string', maxLength: 500, minLength: 5),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ngày công sau điều chỉnh'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền attendance.manage'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function clearDay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return response()->json(['success' => true, 'data' => $this->adjustments->clearDay($data['user_id'], $data['date'], $data['reason'], $request->attributes->get('actor'))]);
    }

    #[OA\Post(
        path: '/attendance/punches/{log}/void',
        summary: 'Hủy một lần chấm công',
        description: 'Yêu cầu quyền attendance.manage. Log vẫn được giữ để đối chiếu, chỉ đánh dấu đã hủy và tính lại công.',
        tags: ['Điều chỉnh công'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'log', in: 'path', required: true, description: 'Id log chấm công', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 500, minLength: 5)])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ngày công sau khi hủy lần chấm'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền attendance.manage'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function voidPunch(Request $request, PunchLog $log): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return response()->json(['success' => true, 'data' => $this->adjustments->voidPunch($log, $data['reason'], $request->attributes->get('actor'))]);
    }

    /** Log thô của một ngày (kể cả log đã hủy) để đối chiếu; xem được: chính mình, quản lý trực tiếp, hoặc có quyền xem công. */
    #[OA\Get(
        path: '/attendance/punches',
        summary: 'Log chấm công thô của một ngày',
        description: 'Gồm cả log đã hủy để đối chiếu. Xem được: chính mình, nhân viên dưới quyền trực tiếp, hoặc người có attendance.view hoặc attendance.manage.',
        tags: ['Điều chỉnh công'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, description: 'Mặc định là người đang đăng nhập', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Các log theo thứ tự thời gian'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function punches(Request $request, IdentityClient $identity): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $data = $request->validate(['user_id' => ['nullable', 'integer'], 'date' => ['required', 'date_format:Y-m-d']]);
        $userId = (int) ($data['user_id'] ?? $actor->id);
        if ($userId !== $actor->id && ! ActorPermissions::has($actor, 'attendance.view', 'attendance.manage')
            && ! in_array($userId, $identity->directReportIds($actor->id), true)) {
            throw new AccessDeniedHttpException('Bạn không có quyền xem log chấm công của nhân viên này.');
        }

        return response()->json(['success' => true, 'data' => PunchLog::where('user_id', $userId)->whereDate('work_date', $data['date'])->orderBy('punched_at')->get()]);
    }

    #[OA\Get(
        path: '/attendance/audit-logs',
        summary: 'Nhật ký điều chỉnh công',
        description: 'Yêu cầu quyền attendance.view hoặc attendance.manage. 200 bản ghi gần nhất.',
        tags: ['Điều chỉnh công'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'month', in: 'query', required: false, description: 'YYYY-MM', schema: new OA\Schema(type: 'string', example: '2026-10')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Nhật ký điều chỉnh'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền attendance.view hoặc attendance.manage'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function auditLogs(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer'], 'month' => ['nullable', 'date_format:Y-m']]);
        $query = AttendanceAuditLog::orderByDesc('id')->limit(200);
        if (! empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }
        if (! empty($data['month'])) {
            $start = CarbonImmutable::createFromFormat('!Y-m', $data['month']);
            $query->whereBetween('work_date', [$start->toDateString(), $start->endOfMonth()->toDateString()]);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }
}
