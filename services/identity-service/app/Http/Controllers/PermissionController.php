<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PermissionController extends Controller
{
    #[OA\Get(
        path: '/permissions',
        summary: 'Danh sách quyền hạn',
        tags: ['Quyền hạn'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách quyền, sắp xếp theo module và mã'),
            new OA\Response(response: 401, description: 'AUTH_TOKEN_MISSING, AUTH_TOKEN_INVALID hoặc AUTH_TOKEN_EXPIRED: thiếu, sai hoặc hết hạn token'),
        ]
    )]
    public function index()
    {
        $perms = DB::table('permissions')->orderBy('module')->orderBy('code')->get();

        return response()->json(['success' => true, 'data' => $perms]);
    }

    #[OA\Post(
        path: '/permissions',
        summary: 'Tạo quyền hạn',
        description: 'Yêu cầu quyền permission.manage. Mã và module được chuyển thành chữ thường.',
        tags: ['Quyền hạn'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'module', 'description'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', maxLength: 100, example: 'finance.view'),
                    new OA\Property(property: 'module', type: 'string', maxLength: 50, example: 'finance'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 255),
                ]
            )
        ),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Tạo thành công, trả về id mới'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền permission.manage'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: trùng mã hoặc dữ liệu sai'),
        ]
    )]
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

    #[OA\Put(
        path: '/permissions/{id}',
        summary: 'Cập nhật quyền hạn',
        description: 'Yêu cầu quyền permission.manage.',
        tags: ['Quyền hạn'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'code', type: 'string', maxLength: 100),
                    new OA\Property(property: 'module', type: 'string', maxLength: 50),
                    new OA\Property(property: 'description', type: 'string', maxLength: 255),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật thành công'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền permission.manage'),
            new OA\Response(response: 404, description: 'Không tìm thấy quyền hạn'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: trùng mã hoặc dữ liệu sai'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $perm = DB::table('permissions')->where('id', $id)->first();
        if (! $perm) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy quyền hạn'], 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:100|unique:permissions,code,'.$id,
            'module' => 'sometimes|required|string|max:50',
            'description' => 'sometimes|required|string|max:255',
        ]);

        $updateData = [];
        if (isset($validated['code'])) {
            $updateData['code'] = strtolower(trim($validated['code']));
        }
        if (isset($validated['module'])) {
            $updateData['module'] = strtolower(trim($validated['module']));
        }
        if (isset($validated['description'])) {
            $updateData['description'] = $validated['description'];
        }
        $updateData['updated_at'] = now();

        DB::table('permissions')->where('id', $id)->update($updateData);

        return response()->json(['success' => true, 'message' => 'Cập nhật quyền hạn thành công']);
    }

    #[OA\Delete(
        path: '/permissions/{id}',
        summary: 'Xóa quyền hạn',
        description: 'Yêu cầu quyền permission.manage. Đồng thời gỡ quyền này khỏi mọi vai trò.',
        tags: ['Quyền hạn'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền permission.manage'),
            new OA\Response(response: 404, description: 'Không tìm thấy quyền hạn'),
        ]
    )]
    public function destroy($id)
    {
        $perm = DB::table('permissions')->where('id', $id)->first();
        if (! $perm) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy quyền hạn'], 404);
        }

        DB::table('role_permissions')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa quyền hạn']);
    }
}
