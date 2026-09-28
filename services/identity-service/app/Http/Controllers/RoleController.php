<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:roles,code',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!empty($validated['permission_ids'])) {
            foreach ($validated['permission_ids'] as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permId,
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Tạo vai trò thành công', 'data' => ['id' => $roleId]], 201);
    }

    public function update(Request $request, $id)
    {
        $role = DB::table('roles')->where('id', $id)->first();
        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy vai trò'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string|max:255',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (array_key_exists('description', $validated)) $updateData['description'] = $validated['description'];
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

    public function destroy($id)
    {
        $role = DB::table('roles')->where('id', $id)->first();
        if (!$role) {
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

    public function updatePermissions(Request $request, $id)
    {
        $role = DB::table('roles')->where('id', $id)->first();
        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy vai trò'], 404);
        }

        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id'
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
