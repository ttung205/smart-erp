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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** HR thêm / xóa công thủ công và tra cứu log chấm công, nhật ký thay đổi. */
class AttendanceAdjustmentController extends Controller
{
    public function __construct(private readonly AttendanceAdjustmentService $adjustments) {}

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

    public function clearDay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return response()->json(['success' => true, 'data' => $this->adjustments->clearDay($data['user_id'], $data['date'], $data['reason'], $request->attributes->get('actor'))]);
    }

    public function voidPunch(Request $request, PunchLog $log): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return response()->json(['success' => true, 'data' => $this->adjustments->voidPunch($log, $data['reason'], $request->attributes->get('actor'))]);
    }

    /** Log thô của một ngày (kể cả log đã hủy) để đối chiếu; xem được: chính mình, quản lý trực tiếp, hoặc có quyền xem công. */
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
