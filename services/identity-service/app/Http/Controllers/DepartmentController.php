<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = DB::table('departments')->orderBy('id')->get();
        $userCounts = DB::table('users')
            ->select('department_id', DB::raw('count(id) as total_users'))
            ->groupBy('department_id')
            ->pluck('total_users', 'department_id');

        foreach ($departments as $d) {
            $d->users_count = (int) ($userCounts[$d->id] ?? 0);
        }
        return response()->json(['success' => true, 'data' => $departments]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:departments,code',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
        ]);

        $id = DB::table('departments')->insertGetId([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Tạo phòng ban thành công', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, $id)
    {
        $dept = DB::table('departments')->where('id', $id)->first();
        if (!$dept) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phòng ban'], 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:departments,code,' . $id,
            'name' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string|max:255',
        ]);

        $updateData = [];
        if (isset($validated['code'])) $updateData['code'] = strtoupper(trim($validated['code']));
        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (array_key_exists('description', $validated)) $updateData['description'] = $validated['description'];
        $updateData['updated_at'] = now();

        DB::table('departments')->where('id', $id)->update($updateData);

        return response()->json(['success' => true, 'message' => 'Cập nhật phòng ban thành công']);
    }

    public function destroy($id)
    {
        $dept = DB::table('departments')->where('id', $id)->first();
        if (!$dept) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phòng ban'], 404);
        }

        $hasUsers = DB::table('users')->where('department_id', $id)->exists();
        if ($hasUsers) {
            return response()->json(['success' => false, 'message' => 'Không thể xóa phòng ban đang có nhân viên! Vui lòng chuyển nhân sự sang phòng ban khác trước.'], 422);
        }

        DB::table('departments')->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa phòng ban thành công']);
    }
}
