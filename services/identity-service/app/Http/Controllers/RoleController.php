<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    #[OA\Get(
        path: '/roles',
        summary: 'Danh sách vai trò',
        tags: ['Vai trò'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách vai trò kèm quyền (permissions, permission_codes) và số người dùng'),
            new OA\Response(response: 401, description: 'AUTH_TOKEN_MISSING, AUTH_TOKEN_INVALID hoặc AUTH_TOKEN_EXPIRED: thiếu, sai hoặc hết hạn token'),
        ]
    )]
    public function index()
    {
        $roles = DB::table('roles')->orderBy('id')->get();

        $userCounts = DB::table('user_roles')
            ->select('role_id', DB::raw('count(user_id) as total_users'))
            ->groupBy('role_id')
            ->pluck('total_users', 'role_id');

        $rolePerms = DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->select('role_permissions.role_id', 'permissions.id as permission_id', 'permissions.code', 'permissions.description', 'permissions.module')
            ->get()
            ->groupBy('role_id');

        foreach ($roles as $r) {
            $r->users_count = (int) ($userCounts[$r->id] ?? 0);
            $perms = $rolePerms->get($r->id, collect());
            $r->permissions = $perms->values()->all();
            $r->permission_ids = $perms->pluck('permission_id')->all();
            $r->permission_codes = $perms->pluck('code')->all();
        }

        return response()->json(['success' => true, 'data' => $roles]);
    }

    #[OA\Post(
        path: '/roles',
        summary: 'Tạo vai trò',
        description: 'Yêu cầu quyền role.manage. Mã vai trò được chuyển thành chữ hoa và phải duy nhất.',
        tags: ['Vai trò'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'name'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', maxLength: 50, example: 'ACCOUNTANT'),
                    new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Kế toán'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'permission_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Tạo thành công, trả về id mới'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền role.manage'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: trùng mã hoặc quyền không tồn tại'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:roles,code',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! empty($validated['permission_ids'])) {
            foreach ($validated['permission_ids'] as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permId,
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Tạo vai trò thành công', 'data' => ['id' => $roleId]], 201);
    }

    #[OA\Put(
        path: '/roles/{id}',
        summary: 'Cập nhật vai trò',
        description: 'Yêu cầu quyền role.manage. Nếu gửi permission_ids thì thay toàn bộ danh sách quyền của vai trò.',
        tags: ['Vai trò'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 150),
                    new OA\Property(property: 'description', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'permission_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật thành công'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền role.manage'),
            new OA\Response(response: 404, description: 'Không tìm thấy vai trò'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: quyền không tồn tại'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $role = DB::table('roles')->where('id', $id)->first();
        if (! $role) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy vai trò'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string|max:255',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $updateData = [];
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        $updateData['updated_at'] = now();

        DB::table('roles')->where('id', $id)->update($updateData);

        if (isset($validated['permission_ids'])) {
            DB::table('role_permissions')->where('role_id', $id)->delete();
            foreach ($validated['permission_ids'] as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $id,
                    'permission_id' => $permId,
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Cập nhật vai trò thành công']);
    }

    #[OA\Delete(
        path: '/roles/{id}',
        summary: 'Xóa vai trò',
        description: 'Yêu cầu quyền role.manage. Không cho xóa vai trò SUPER_ADMIN. Gỡ vai trò khỏi mọi người dùng.',
        tags: ['Vai trò'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 400, description: 'Không được xóa vai trò SUPER_ADMIN'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền role.manage'),
            new OA\Response(response: 404, description: 'Không tìm thấy vai trò'),
        ]
    )]
    public function destroy($id)
    {
        $role = DB::table('roles')->where('id', $id)->first();
        if (! $role) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy vai trò'], 404);
        }
        if ($role->code === 'SUPER_ADMIN') {
            return response()->json(['success' => false, 'message' => 'Không được phép xóa vai trò Super Admin'], 400);
        }

        DB::table('role_permissions')->where('role_id', $id)->delete();
        DB::table('user_roles')->where('role_id', $id)->delete();
        DB::table('roles')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa vai trò']);
    }

    #[OA\Put(
        path: '/roles/{id}/permissions',
        summary: 'Gán lại danh sách quyền cho vai trò',
        description: 'Yêu cầu quyền role.manage. Thay toàn bộ quyền hiện có bằng danh sách gửi lên.',
        tags: ['Vai trò'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['permission_ids'],
                properties: [
                    new OA\Property(property: 'permission_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Đã cập nhật danh sách quyền'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền role.manage'),
            new OA\Response(response: 404, description: 'Không tìm thấy vai trò'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: thiếu permission_ids hoặc quyền không tồn tại'),
        ]
    )]
    public function updatePermissions(Request $request, $id)
    {
        $role = DB::table('roles')->where('id', $id)->first();
        if (! $role) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy vai trò'], 404);
        }

        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        DB::table('role_permissions')->where('role_id', $id)->delete();
        foreach ($validated['permission_ids'] as $permId) {
            DB::table('role_permissions')->insert([
                'role_id' => $id,
                'permission_id' => $permId,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Đã cập nhật danh sách quyền cho vai trò']);
    }
}
