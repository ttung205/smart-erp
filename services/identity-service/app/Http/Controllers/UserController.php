<?php

namespace App\Http\Controllers;

use App\Services\EmployeeCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/users',
        summary: 'Danh sách người dùng',
        description: 'Có thể lọc theo danh sách id bằng ?ids=1,2,3 (dùng cho service khác tra cứu theo lô).',
        tags: ['Người dùng'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        parameters: [
            new OA\Parameter(
                name: 'ids',
                in: 'query',
                required: false,
                description: 'Danh sách id phân tách bằng dấu phẩy',
                schema: new OA\Schema(type: 'string', example: '1,2,3')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách người dùng kèm vai trò (roles, role_ids)'),
            new OA\Response(response: 401, description: 'AUTH_TOKEN_MISSING, AUTH_TOKEN_INVALID hoặc AUTH_TOKEN_EXPIRED: thiếu, sai hoặc hết hạn token'),
        ]
    )]
    public function index(Request $request)
    {
        $query = $this->userQuery()->orderBy('users.id');
        // Batch lookup cho service khác: ?ids=1,2,3
        if ($request->filled('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', (string) $request->input('ids'));
            $query->whereIn('users.id', array_map('intval', $ids));
        }
        $users = $query->get();

        $userRoles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->select('user_roles.user_id', 'roles.id as role_id', 'roles.code', 'roles.name')
            ->get()
            ->groupBy('user_id');

        foreach ($users as $user) {
            $user->roles = $userRoles->get($user->id, collect())->values()->all();
            $user->role_ids = collect($user->roles)->pluck('role_id')->all();
        }

        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * Danh sách user đang hoạt động có ít nhất một trong các quyền (?permissions=a.approve,b.manage).
     * Quyền "<module>.manage" bao hàm mọi quyền cùng module, giống RequirePermission ở các service.
     * Dùng cho notification-service xác định người nhận thông báo.
     */
    #[OA\Get(
        path: '/users/by-permission',
        summary: 'Tìm người dùng theo quyền',
        description: 'Trả về người dùng đang hoạt động có ít nhất một quyền được yêu cầu. Quyền "<module>.manage" bao hàm mọi quyền cùng module.',
        tags: ['Người dùng'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        parameters: [
            new OA\Parameter(
                name: 'permissions',
                in: 'query',
                required: true,
                description: 'Mã quyền phân tách bằng dấu phẩy',
                schema: new OA\Schema(type: 'string', example: 'eoffice.approve,finance.view')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách người dùng (id, name, email)'),
            new OA\Response(response: 422, description: 'PERMISSIONS_REQUIRED: thiếu tham số permissions'),
        ]
    )]
    public function byPermission(Request $request)
    {
        $requested = collect(explode(',', (string) $request->query('permissions', '')))
            ->map(fn ($code) => trim($code))
            ->filter()
            ->values();
        if ($requested->isEmpty()) {
            return response()->json(['success' => false, 'error' => ['code' => 'PERMISSIONS_REQUIRED']], 422);
        }

        $codes = $requested
            ->flatMap(fn ($code) => str_contains($code, '.') ? [$code, strstr($code, '.', true).'.manage'] : [$code])
            ->unique()
            ->values()
            ->all();

        $users = DB::table('users')
            ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('permissions.code', $codes)
            ->where('users.is_active', true)
            ->select('users.id', 'users.employee_code', 'users.name', 'users.email')
            ->distinct()
            ->orderBy('users.id')
            ->get();

        return response()->json(['success' => true, 'data' => $users]);
    }

    #[OA\Get(
        path: '/users/{id}',
        summary: 'Chi tiết người dùng',
        tags: ['Người dùng'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Thông tin người dùng, phòng ban và quản lý trực tiếp'),
            new OA\Response(response: 404, description: 'Không tìm thấy người dùng'),
        ]
    )]
    public function show($id)
    {
        $user = $this->userQuery()->where('users.id', $id)->first();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy người dùng'], 404);
        }

        return response()->json(['success' => true, 'data' => $user]);
    }

    #[OA\Post(
        path: '/users',
        summary: 'Tạo người dùng',
        description: 'Yêu cầu quyền employee.manage hoặc users.manage. Mã nhân viên được tự cấp theo tiền tố cấu hình ở công ty. Nếu không gửi mật khẩu, tài khoản nhận mật khẩu ngẫu nhiên không ai biết và đăng nhập bằng OTP.',
        tags: ['Người dùng'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Nguyễn Văn A'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 150),
                    new OA\Property(property: 'password', type: 'string', format: 'password', nullable: true, minLength: 6),
                    new OA\Property(property: 'department_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'line_manager_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
                    new OA\Property(property: 'role_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Tạo thành công, trả về id mới'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền employee.manage hoặc users.manage'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: email trùng hoặc dữ liệu sai'),
        ]
    )]
    public function store(Request $request, EmployeeCodeService $codes)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'nullable|string|min:6',
            'department_id' => 'nullable|exists:departments,id',
            'line_manager_id' => 'nullable|exists:users,id',
            'is_active' => 'nullable|boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $id = DB::table('users')->insertGetId([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password_hash' => Hash::make($validated['password'] ?? Str::random(40)),
            'department_id' => $validated['department_id'] ?? null,
            'line_manager_id' => $validated['line_manager_id'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? ($validated['is_active'] ? 1 : 0) : 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $codes->assign((int) $id);

        if (! empty($validated['role_ids'])) {
            foreach ($validated['role_ids'] as $roleId) {
                DB::table('user_roles')->insert([
                    'user_id' => $id,
                    'role_id' => $roleId,
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Tạo nhân viên thành công', 'data' => ['id' => $id]], 201);
    }

    #[OA\Put(
        path: '/users/{id}',
        summary: 'Cập nhật người dùng',
        description: 'Yêu cầu quyền employee.manage hoặc users.manage. Không cho chọn cấp dưới làm quản lý trực tiếp để tránh vòng lặp duyệt.',
        tags: ['Người dùng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 150),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 150),
                    new OA\Property(property: 'password', type: 'string', format: 'password', nullable: true, minLength: 6, description: 'Bỏ trống để giữ mật khẩu cũ'),
                    new OA\Property(property: 'department_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'line_manager_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
                    new OA\Property(property: 'role_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật thành công'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền employee.manage hoặc users.manage'),
            new OA\Response(response: 404, description: 'Không tìm thấy người dùng'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED, hoặc chọn cấp dưới làm quản lý (tạo vòng lặp)'),
        ]
    )]
    public function update(Request $request, $id, EmployeeCodeService $codes)
    {
        $user = DB::table('users')->where('id', $id)->first();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy người dùng'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'email' => 'sometimes|required|email|max:150|unique:users,email,'.$id,
            'employee_code' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z]{1,2}-\d{4}$/', Rule::unique('users', 'employee_code')->ignore($id)],
            'password' => 'nullable|string|min:6',
            'department_id' => 'nullable|exists:departments,id',
            'line_manager_id' => 'nullable|exists:users,id|not_in:'.$id,
            'is_active' => 'nullable|boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ], [
            'line_manager_id.not_in' => 'Nhân viên không thể là quản lý trực tiếp của chính mình.',
            'employee_code.regex' => 'Mã nhân viên gồm tiền tố chữ cái và 4 chữ số, ví dụ AC-2005.',
            'employee_code.unique' => 'Mã nhân viên này đã có người dùng.',
        ]);

        $emailChanged = isset($validated['email']) && $validated['email'] !== $user->email;
        $codeChanged = isset($validated['employee_code']) && strtoupper($validated['employee_code']) !== $user->employee_code;
        if (($emailChanged || $codeChanged) && ! $request->attributes->get('actor')->hasAnyRole('SUPER_ADMIN')) {
            // Email và mã nhân viên là thông tin đăng nhập (OTP gửi về email): chỉ Super Admin được đổi.
            return response()->json(['success' => false, 'error' => [
                'code' => 'PERMISSION_DENIED',
                'message' => 'Chỉ Super Admin được đổi email hoặc mã nhân viên.',
            ]], 403);
        }
        if ($codeChanged) {
            $prefix = $codes->prefix();
            if ($prefix === null || strtoupper(strstr($validated['employee_code'], '-', true)) !== $prefix) {
                return response()->json(['success' => false, 'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Mã nhân viên phải bắt đầu bằng tiền tố công ty'.($prefix ? " ({$prefix})." : '. Công ty chưa cấu hình tiền tố.'),
                ]], 422);
            }
        }

        if (! empty($validated['line_manager_id']) && $this->managesIndirectly((int) $id, (int) $validated['line_manager_id'])) {
            return response()->json(['success' => false, 'message' => 'Không thể chọn cấp dưới làm quản lý trực tiếp (tạo vòng lặp duyệt).'], 422);
        }

        $updateData = [];
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (isset($validated['email'])) {
            $updateData['email'] = $validated['email'];
        }
        if ($codeChanged) {
            $updateData['employee_code'] = strtoupper($validated['employee_code']);
        }
        if (! empty($validated['password'])) {
            $updateData['password_hash'] = Hash::make($validated['password']);
        }
        if (array_key_exists('department_id', $validated)) {
            $updateData['department_id'] = $validated['department_id'];
        }
        if (array_key_exists('line_manager_id', $validated)) {
            $updateData['line_manager_id'] = $validated['line_manager_id'];
        }
        if (array_key_exists('is_active', $validated)) {
            $updateData['is_active'] = $validated['is_active'] ? 1 : 0;
        }
        $updateData['updated_at'] = now();

        DB::table('users')->where('id', $id)->update($updateData);
        if ($emailChanged || $codeChanged) {
            // OTP đang chờ gửi tới email cũ không còn giá trị.
            DB::table('login_otps')->where('user_id', $id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        }

        if (isset($validated['role_ids'])) {
            DB::table('user_roles')->where('user_id', $id)->delete();
            foreach ($validated['role_ids'] as $roleId) {
                DB::table('user_roles')->insert([
                    'user_id' => $id,
                    'role_id' => $roleId,
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Cập nhật nhân viên thành công']);
    }

    #[OA\Delete(
        path: '/users/{id}',
        summary: 'Xóa người dùng',
        description: 'Yêu cầu quyền employee.manage hoặc users.manage. Gỡ toàn bộ vai trò của người dùng trước khi xóa.',
        tags: ['Người dùng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền employee.manage hoặc users.manage'),
            new OA\Response(response: 404, description: 'Không tìm thấy người dùng'),
        ]
    )]
    public function destroy($id)
    {
        $user = DB::table('users')->where('id', $id)->first();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy người dùng'], 404);
        }

        DB::table('user_roles')->where('user_id', $id)->delete();
        DB::table('users')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa người dùng thành công']);
    }

    private function userQuery()
    {
        return DB::table('users')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('users as line_managers', 'users.line_manager_id', '=', 'line_managers.id')
            ->select(
                'users.id',
                'users.employee_code',
                'users.name',
                'users.email',
                'users.is_active',
                'users.department_id',
                'departments.name as department_name',
                'departments.code as department_code',
                'users.line_manager_id',
                'line_managers.name as line_manager_name',
                'users.created_at'
            );
    }

    /** $userId có đang là cấp trên (trực tiếp hoặc gián tiếp) của $candidateId không. */
    private function managesIndirectly(int $userId, int $candidateId): bool
    {
        $visited = [];
        $current = $candidateId;
        while ($current && ! in_array($current, $visited, true)) {
            if ($current === $userId) {
                return true;
            }
            $visited[] = $current;
            $current = (int) DB::table('users')->where('id', $current)->value('line_manager_id');
        }

        return false;
    }
}
