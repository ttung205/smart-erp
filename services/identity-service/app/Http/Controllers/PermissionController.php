<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function index()
    {
        $perms = DB::table('permissions')->orderBy('module')->orderBy('code')->get();
        return response()->json(['success' => true, 'data' => $perms]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:100|unique:permissions,code',
            'module' => 'required|string|max:50',
            'description' => 'required|string|max:255',
        ]);

        $id = DB::table('permissions')->insertGetId([
            'code' => strtolower(trim($validated['code'])),
            'module' => strtolower(trim($validated['module'])),
            'description' => $validated['description'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Tạo quyền hạn thành công', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, $id)
    {
        $perm = DB::table('permissions')->where('id', $id)->first();
        if (!$perm) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy quyền hạn'], 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:100|unique:permissions,code,' . $id,
            'module' => 'sometimes|required|string|max:50',
            'description' => 'sometimes|required|string|max:255',
        ]);

        $updateData = [];
        if (isset($validated['code'])) $updateData['code'] = strtolower(trim($validated['code']));
        if (isset($validated['module'])) $updateData['module'] = strtolower(trim($validated['module']));
        if (isset($validated['description'])) $updateData['description'] = $validated['description'];
        $updateData['updated_at'] = now();

        DB::table('permissions')->where('id', $id)->update($updateData);

        return response()->json(['success' => true, 'message' => 'Cập nhật quyền hạn thành công']);
    }

    public function destroy($id)
    {
        $perm = DB::table('permissions')->where('id', $id)->first();
        if (!$perm) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy quyền hạn'], 404);
        }

        DB::table('role_permissions')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa quyền hạn']);
    }
}
