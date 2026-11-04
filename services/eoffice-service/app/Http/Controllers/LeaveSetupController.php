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
use OpenApi\Attributes as OA;

/** Danh mục nghỉ phép (loại nghỉ, ngày lễ), quỹ phép và lịch nghỉ của phòng. */
#[OA\Schema(
    schema: 'LeaveTypeInput',
    required: ['code', 'name'],
    properties: [
        new OA\Property(property: 'code', type: 'string', maxLength: 30, description: 'Tự chuyển thành chữ hoa, phải duy nhất'),
        new OA\Property(property: 'name', type: 'string', maxLength: 100),
        new OA\Property(property: 'is_paid', type: 'boolean'),
        new OA\Property(property: 'deducts_balance', type: 'boolean', description: 'Trừ vào quỹ phép năm'),
        new OA\Property(property: 'requires_attachment', type: 'boolean'),
        new OA\Property(property: 'is_active', type: 'boolean'),
        new OA\Property(property: 'max_days_per_request', type: 'number', nullable: true, exclusiveMinimum: 0),
    ],
    type: 'object'
)]
class LeaveSetupController extends Controller
{
    #[OA\Get(
        path: '/leave-types',
        summary: 'Danh mục loại nghỉ',
        tags: ['Danh mục nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'include_inactive', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: false))],
        responses: [
            new OA\Response(response: 200, description: 'Các loại nghỉ'),
            new OA\Response(response: 401, description: self::DOC_401),
        ]
    )]
    public function leaveTypes(Request $request): JsonResponse
    {
        $types = LeaveType::query()->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => $types]);
    }

    #[OA\Post(
        path: '/leave-types',
        summary: 'Tạo loại nghỉ',
        description: 'Yêu cầu quyền eoffice.manage.',
        tags: ['Danh mục nghỉ phép'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LeaveTypeInput')),
        responses: [
            new OA\Response(response: 201, description: 'Loại nghỉ mới'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền eoffice.manage'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    #[OA\Put(
        path: '/leave-types/{leaveType}',
        summary: 'Cập nhật loại nghỉ',
        description: 'Yêu cầu quyền eoffice.manage.',
        tags: ['Danh mục nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'leaveType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LeaveTypeInput')),
        responses: [
            new OA\Response(response: 200, description: 'Loại nghỉ sau cập nhật'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền eoffice.manage'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
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

    #[OA\Get(
        path: '/holidays',
        summary: 'Danh sách ngày lễ trong năm',
        tags: ['Danh mục nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Mặc định năm hiện tại', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Ngày lễ theo thứ tự ngày'),
            new OA\Response(response: 401, description: self::DOC_401),
        ]
    )]
    public function holidays(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) today()->year);

        return response()->json(['success' => true, 'data' => Holiday::whereYear('date', $year)->orderBy('date')->get()]);
    }

    #[OA\Post(
        path: '/holidays',
        summary: 'Thêm ngày lễ',
        description: 'Yêu cầu quyền eoffice.manage. Mỗi ngày chỉ một ngày lễ.',
        tags: ['Danh mục nghỉ phép'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['date', 'name'],
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                    new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Quốc khánh'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ngày lễ mới'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền eoffice.manage'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function storeHoliday(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'unique:holidays,date'], 'name' => ['required', 'string', 'max:150'],
        ]);

        return response()->json(['success' => true, 'data' => Holiday::create($data)], 201);
    }

    #[OA\Delete(
        path: '/holidays/{holiday}',
        summary: 'Xóa ngày lễ',
        description: 'Yêu cầu quyền eoffice.manage.',
        tags: ['Danh mục nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'holiday', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền eoffice.manage'),
            new OA\Response(response: 404, description: self::DOC_404),
        ]
    )]
    public function destroyHoliday(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return response()->json(['success' => true]);
    }

    #[OA\Get(
        path: '/leave-balances/me',
        summary: 'Quỹ phép của tôi',
        tags: ['Quỹ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Mặc định năm hiện tại', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Số phép đầu kỳ, đã dùng, đang chờ duyệt và còn lại'),
            new OA\Response(response: 401, description: self::DOC_401),
        ]
    )]
    public function myBalance(Request $request, LeaveBalanceService $balances): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $balance = $balances->forUser($actor->id, $request->integer('year', (int) today()->year));

        return response()->json(['success' => true, 'data' => $balances->present($balance)]);
    }

    /** Bảng quỹ phép toàn công ty: mọi nhân viên đang hoạt động trong identity-service. */
    #[OA\Get(
        path: '/leave-balances',
        summary: 'Bảng quỹ phép toàn công ty',
        description: 'Yêu cầu quyền eoffice.manage. Lấy danh sách nhân viên đang hoạt động từ identity-service.',
        tags: ['Quỹ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Mặc định năm hiện tại', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Quỹ phép từng nhân viên kèm tên, email, phòng ban'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền eoffice.manage'),
            new OA\Response(response: 422, description: 'Không kết nối được identity-service'),
        ]
    )]
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
    #[OA\Put(
        path: '/leave-balances/{userId}',
        summary: 'Đặt quỹ phép đầu kỳ cho nhân viên',
        description: 'Yêu cầu quyền eoffice.manage. Bỏ trống opening_days thì tự tính theo tỷ lệ số tháng làm việc từ period_start.',
        tags: ['Quỹ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['year', 'period_start'],
                properties: [
                    new OA\Property(property: 'year', type: 'integer', maximum: 2100, minimum: 2000),
                    new OA\Property(property: 'period_start', type: 'string', format: 'date', description: 'Phải nằm trong year'),
                    new OA\Property(property: 'opening_days', type: 'number', nullable: true, maximum: 365, minimum: 0),
                    new OA\Property(property: 'note', type: 'string', nullable: true, maxLength: 255),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Quỹ phép sau cập nhật'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền eoffice.manage'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
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

    #[OA\Get(
        path: '/leave-balances/pro-rata',
        summary: 'Tính số phép đầu kỳ theo tỷ lệ',
        description: 'Yêu cầu quyền eoffice.manage. Tính số phép theo số tháng làm việc tính từ period_start đến cuối năm.',
        tags: ['Quỹ phép'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period_start', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'))],
        responses: [
            new OA\Response(response: 200, description: 'opening_days'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền eoffice.manage'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function proRata(Request $request, LeaveBalanceService $balances): JsonResponse
    {
        $data = $request->validate(['period_start' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['success' => true, 'data' => ['opening_days' => $balances->proRataDays($data['period_start'])]]);
    }

    /** Các đơn nghỉ trong năm của một nhân viên (xem chi tiết từ bảng quỹ phép). */
    #[OA\Get(
        path: '/leave-balances/{userId}/requests',
        summary: 'Đơn nghỉ trong năm của một nhân viên',
        description: 'Yêu cầu quyền eoffice.manage. Không gồm đơn nháp.',
        tags: ['Quỹ phép'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Mặc định năm hiện tại', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Đơn nghỉ kèm loại nghỉ'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền eoffice.manage'),
        ]
    )]
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
    #[OA\Get(
        path: '/leave-calendar',
        summary: 'Lịch nghỉ trong tháng',
        description: 'Đơn đang chờ duyệt và đã duyệt. Nhân viên thường chỉ thấy phòng ban của mình; SUPER_ADMIN hoặc eoffice.manage thấy toàn công ty và lọc được theo department_id.',
        tags: ['Nghỉ phép'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'month', in: 'query', required: false, description: 'YYYY-MM, mặc định tháng hiện tại', schema: new OA\Schema(type: 'string', example: '2026-10')),
            new OA\Parameter(name: 'department_id', in: 'query', required: false, description: 'Chỉ có tác dụng với người xem được toàn công ty', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Các đơn nghỉ trong tháng và cờ can_view_all'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
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
