<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use App\Models\Shift;
use App\Services\IdentityClient;
use App\Support\ActorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Hồ sơ nhân sự phục vụ chấm công / lương và danh mục ca làm việc (HR). */
class EmployeeProfileController extends Controller
{
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

    public function shifts(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Shift::orderBy('code')->get()]);
    }

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
