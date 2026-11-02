<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use App\Models\Shift;
use App\Services\IdentityClient;
use App\Support\ActorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

/** Hồ sơ nhân sự phục vụ chấm công / lương và danh mục ca làm việc (HR). */
#[OA\Schema(
    schema: 'ShiftInput',
    required: ['code', 'name', 'start_time', 'end_time', 'grace_minutes', 'full_day_minutes', 'half_day_minutes', 'saturday_mode'],
    properties: [
        new OA\Property(property: 'code', type: 'string', maxLength: 30),
        new OA\Property(property: 'name', type: 'string', maxLength: 100),
        new OA\Property(property: 'start_time', type: 'string', example: '08:00'),
        new OA\Property(property: 'end_time', type: 'string', example: '17:30', description: 'Sau start_time'),
        new OA\Property(property: 'break_start', type: 'string', nullable: true, example: '12:00'),
        new OA\Property(property: 'break_end', type: 'string', nullable: true, example: '13:30'),
        new OA\Property(property: 'grace_minutes', type: 'integer', maximum: 120, minimum: 0, description: 'Số phút đi muộn được bỏ qua'),
        new OA\Property(property: 'full_day_minutes', type: 'integer', maximum: 720, minimum: 60),
        new OA\Property(property: 'half_day_minutes', type: 'integer', minimum: 30, description: 'Không lớn hơn full_day_minutes'),
        new OA\Property(property: 'saturday_mode', type: 'string', enum: ['OFF', 'HALF', 'FULL']),
        new OA\Property(property: 'is_default', type: 'boolean', description: 'Đặt làm ca mặc định thì ca mặc định cũ bị bỏ'),
        new OA\Property(property: 'is_active', type: 'boolean'),
    ],
    type: 'object'
)]
class EmployeeProfileController extends Controller
{
    #[OA\Get(
        path: '/employee-profiles',
        summary: 'Danh sách hồ sơ nhân sự',
        description: 'Yêu cầu quyền attendance.view hoặc attendance.manage. Lương và tài khoản ngân hàng chỉ hiện với người có payroll.manage.',
        tags: ['Hồ sơ nhân sự và ca'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Hồ sơ kèm ca làm việc, sắp xếp theo mã nhân viên'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền attendance.view hoặc attendance.manage'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $profiles = EmployeeProfile::with('shift')->orderBy('employee_code')->get();
        if (ActorPermissions::hasStrict($actor, 'payroll.manage')) {
            $profiles->each->makeVisible(['base_salary', 'insurance_salary', 'fixed_allowance', 'bank_name', 'bank_account']);
        }

        return response()->json(['success' => true, 'data' => $profiles]);
    }

    /** Tạo hồ sơ còn thiếu cho mọi tài khoản đang hoạt động bên identity-service (mã NV mặc định NV0001…). */
    #[OA\Post(
        path: '/employee-profiles/sync',
        summary: 'Đồng bộ hồ sơ từ identity-service',
        description: 'Yêu cầu quyền attendance.manage. Tạo hồ sơ còn thiếu cho mọi tài khoản đang hoạt động, mã nhân viên mặc định NV + id 4 chữ số, gán ca mặc định.',
        tags: ['Hồ sơ nhân sự và ca'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Số hồ sơ mới được tạo'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền attendance.manage'),
            new OA\Response(response: 422, description: 'Không kết nối được identity-service'),
        ]
    )]
    public function sync(IdentityClient $identity): JsonResponse
    {
        $created = 0;
        $defaultShiftId = Shift::where('is_default', true)->value('id');
        foreach ($identity->users() as $user) {
            if (! ($user['is_active'] ?? true) || EmployeeProfile::where('user_id', $user['id'])->exists()) {
                continue;
            }
            EmployeeProfile::create([
                'user_id' => $user['id'], 'user_name' => (string) ($user['name'] ?? ''),
                'employee_code' => sprintf('NV%04d', $user['id']), 'shift_id' => $defaultShiftId,
            ]);
            $created++;
        }

        return response()->json(['success' => true, 'data' => ['created' => $created]]);
    }

    #[OA\Put(
        path: '/employee-profiles/{profile}',
        summary: 'Cập nhật hồ sơ nhân sự',
        description: 'Yêu cầu quyền attendance.manage. Các trường lương và ngân hàng (base_salary, insurance_salary, fixed_allowance, dependents, bank_name, bank_account) chỉ người có payroll.manage được sửa.',
        tags: ['Hồ sơ nhân sự và ca'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'profile', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'employee_code', type: 'string', maxLength: 30),
                    new OA\Property(property: 'hire_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'termination_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'work_mode', type: 'string', enum: ['ONSITE', 'REMOTE', 'HYBRID']),
                    new OA\Property(property: 'shift_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'base_salary', type: 'number', minimum: 0),
                    new OA\Property(property: 'insurance_salary', type: 'number', nullable: true, minimum: 0),
                    new OA\Property(property: 'fixed_allowance', type: 'number', minimum: 0),
                    new OA\Property(property: 'dependents', type: 'integer', maximum: 20, minimum: 0),
                    new OA\Property(property: 'bank_name', type: 'string', nullable: true, maxLength: 100),
                    new OA\Property(property: 'bank_account', type: 'string', nullable: true, maxLength: 50),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Hồ sơ sau cập nhật'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền attendance.manage, hoặc sửa lương khi thiếu payroll.manage'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function update(Request $request, EmployeeProfile $profile): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $data = $request->validate([
            'employee_code' => ['sometimes', 'string', 'max:30', Rule::unique('employee_profiles', 'employee_code')->ignore($profile->id)],
            'hire_date' => ['nullable', 'date_format:Y-m-d'],
            'termination_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:hire_date'],
            'work_mode' => ['sometimes', Rule::in(EmployeeProfile::WORK_MODES)],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'is_active' => ['sometimes', 'boolean'],
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'insurance_salary' => ['nullable', 'numeric', 'min:0'],
            'fixed_allowance' => ['sometimes', 'numeric', 'min:0'],
            'dependents' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50'],
        ]);

        // Lương và tài khoản ngân hàng chỉ người phụ trách lương được sửa
        $salaryFields = ['base_salary', 'insurance_salary', 'fixed_allowance', 'dependents', 'bank_name', 'bank_account'];
        if (array_intersect_key($data, array_flip($salaryFields)) !== [] && ! ActorPermissions::hasStrict($actor, 'payroll.manage')) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Bạn không có quyền sửa thông tin lương.']], 403);
        }

        $profile->update($data);

        return response()->json(['success' => true, 'data' => $profile->fresh('shift')]);
    }

    #[OA\Get(
        path: '/shifts',
        summary: 'Danh mục ca làm việc',
        tags: ['Hồ sơ nhân sự và ca'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Các ca làm việc, sắp xếp theo mã'),
            new OA\Response(response: 401, description: self::DOC_401),
        ]
    )]
    public function shifts(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Shift::orderBy('code')->get()]);
    }

    #[OA\Post(
        path: '/shifts',
        summary: 'Tạo ca làm việc',
        description: 'Yêu cầu quyền attendance.manage.',
        tags: ['Hồ sơ nhân sự và ca'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ShiftInput')),
        responses: [
            new OA\Response(response: 201, description: 'Ca mới'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền attendance.manage'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    #[OA\Put(
        path: '/shifts/{shift}',
        summary: 'Cập nhật ca làm việc',
        description: 'Yêu cầu quyền attendance.manage.',
        tags: ['Hồ sơ nhân sự và ca'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'shift', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ShiftInput')),
        responses: [
            new OA\Response(response: 200, description: 'Ca sau cập nhật'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền attendance.manage'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function saveShift(Request $request, ?Shift $shift = null): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('shifts', 'code')->ignore($shift?->id)],
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'break_start' => ['nullable', 'date_format:H:i'],
            'break_end' => ['nullable', 'date_format:H:i', 'after:break_start'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'full_day_minutes' => ['required', 'integer', 'min:60', 'max:720'],
            'half_day_minutes' => ['required', 'integer', 'min:30', 'lte:full_day_minutes'],
            'saturday_mode' => ['required', Rule::in([Shift::SATURDAY_OFF, Shift::SATURDAY_HALF, Shift::SATURDAY_FULL])],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $shift = $shift ?? new Shift;
        if (! empty($data['is_default'])) {
            Shift::where('is_default', true)->update(['is_default' => false]);
        }
        $shift->fill($data)->save();

        return response()->json(['success' => true, 'data' => $shift], $shift->wasRecentlyCreated ? 201 : 200);
    }
}
