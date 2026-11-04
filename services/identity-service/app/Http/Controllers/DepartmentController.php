<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DepartmentController extends Controller
{
    #[OA\Get(
        path: '/departments',
        summary: 'Danh sách phòng ban',
        tags: ['Phòng ban'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách phòng ban kèm số nhân viên (users_count)'),
            new OA\Response(response: 401, description: 'AUTH_TOKEN_MISSING, AUTH_TOKEN_INVALID hoặc AUTH_TOKEN_EXPIRED: thiếu, sai hoặc hết hạn token'),
        ]
    )]
    public function index()
    {
        $departments = $this->departmentQuery()->orderBy('departments.id')->get();
        $userCounts = DB::table('users')
            ->select('department_id', DB::raw('count(id) as total_users'))
            ->groupBy('department_id')
            ->pluck('total_users', 'department_id');

        foreach ($departments as $d) {
            $d->users_count = (int) ($userCounts[$d->id] ?? 0);
        }

        return response()->json(['success' => true, 'data' => $departments]);
    }

    #[OA\Get(
        path: '/departments/{id}',
        summary: 'Chi tiết phòng ban',
        tags: ['Phòng ban'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Thông tin phòng ban'),
            new OA\Response(response: 404, description: 'Không tìm thấy phòng ban'),
        ]
    )]
    public function show($id)
    {
        $dept = $this->departmentQuery()->where('departments.id', $id)->first();
        if (! $dept) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phòng ban'], 404);
        }

        return response()->json(['success' => true, 'data' => $dept]);
    }

    #[OA\Post(
        path: '/departments',
        summary: 'Tạo phòng ban',
        description: 'Yêu cầu quyền department.manage. Mã phòng ban được chuyển thành chữ hoa và phải duy nhất.',
        tags: ['Phòng ban'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'name'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', maxLength: 50, example: 'KD'),
                    new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Kinh doanh'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'manager_id', type: 'integer', nullable: true, description: 'ID người dùng quản lý'),
                ]
            )
        ),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Tạo thành công, trả về id mới'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền department.manage'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: trùng mã hoặc dữ liệu sai'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:departments,code',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $id = DB::table('departments')->insertGetId([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'manager_id' => $validated['manager_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Tạo phòng ban thành công', 'data' => ['id' => $id]], 201);
    }

    #[OA\Put(
        path: '/departments/{id}',
        summary: 'Cập nhật phòng ban',
        description: 'Yêu cầu quyền department.manage. Chỉ các trường được gửi lên mới được cập nhật.',
        tags: ['Phòng ban'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'code', type: 'string', maxLength: 50),
                    new OA\Property(property: 'name', type: 'string', maxLength: 150),
                    new OA\Property(property: 'description', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'manager_id', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật thành công'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền department.manage'),
            new OA\Response(response: 404, description: 'Không tìm thấy phòng ban'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: trùng mã hoặc dữ liệu sai'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $dept = DB::table('departments')->where('id', $id)->first();
        if (! $dept) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phòng ban'], 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:departments,code,'.$id,
            'name' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $updateData = [];
        if (isset($validated['code'])) {
            $updateData['code'] = strtoupper(trim($validated['code']));
        }
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        if (array_key_exists('manager_id', $validated)) {
            $updateData['manager_id'] = $validated['manager_id'];
        }
        $updateData['updated_at'] = now();

        DB::table('departments')->where('id', $id)->update($updateData);

        return response()->json(['success' => true, 'message' => 'Cập nhật phòng ban thành công']);
    }

    #[OA\Delete(
        path: '/departments/{id}',
        summary: 'Xóa phòng ban',
        description: 'Yêu cầu quyền department.manage. Không xóa được phòng ban còn nhân viên.',
        tags: ['Phòng ban'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền department.manage'),
            new OA\Response(response: 404, description: 'Không tìm thấy phòng ban'),
            new OA\Response(response: 422, description: 'Phòng ban đang có nhân viên'),
        ]
    )]
    public function destroy($id)
    {
        $dept = DB::table('departments')->where('id', $id)->first();
        if (! $dept) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phòng ban'], 404);
        }

        $hasUsers = DB::table('users')->where('department_id', $id)->exists();
        if ($hasUsers) {
            return response()->json(['success' => false, 'message' => 'Không thể xóa phòng ban đang có nhân viên! Vui lòng chuyển nhân sự sang phòng ban khác trước.'], 422);
        }

        DB::table('departments')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa phòng ban thành công']);
    }

    private function departmentQuery()
    {
        return DB::table('departments')
            ->leftJoin('users as managers', 'departments.manager_id', '=', 'managers.id')
            ->select('departments.*', 'managers.name as manager_name', 'managers.email as manager_email');
    }
}
