<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: '/categories',
        summary: 'Danh sách danh mục sản phẩm',
        tags: ['Danh mục'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Danh mục kèm số sản phẩm (products_count), sắp xếp theo tên'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
        ]
    )]
    public function index()
    {
        $cats = DB::table('categories')
            ->leftJoin('products', 'categories.id', '=', 'products.category_id')
            ->select('categories.id', 'categories.code', 'categories.name', 'categories.description', 'categories.created_at', DB::raw('COUNT(products.id) as products_count'))
            ->groupBy('categories.id', 'categories.code', 'categories.name', 'categories.description', 'categories.created_at')
            ->orderBy('categories.name')
            ->get();

        return response()->json(['success' => true, 'data' => $cats]);
    }

    #[OA\Post(
        path: '/categories',
        summary: 'Tạo danh mục sản phẩm',
        description: 'Yêu cầu quyền catalog.create. Mã danh mục được thêm hậu tố ngẫu nhiên; bỏ trống code thì sinh từ tên.',
        tags: ['Danh mục'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Máy hút ẩm'),
                    new OA\Property(property: 'code', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tạo thành công, trả về id và tên'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền catalog.create'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: thiếu tên danh mục'),
        ]
    )]
    public function store(Request $request)
    {
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Tên danh mục không được để trống']], 422);
        }
        $code = trim($request->input('code', '')) ?: Str::slug($name);
        $id = DB::table('categories')->insertGetId([
            'name' => $name,
            'code' => $code.'-'.rand(100, 999),
            'description' => $request->input('description'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $id, 'name' => $name]], 201);
    }

    #[OA\Put(
        path: '/categories/{id}',
        summary: 'Cập nhật danh mục sản phẩm',
        description: 'Yêu cầu quyền catalog.edit. Chấp nhận cả PUT và PATCH.',
        tags: ['Danh mục'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật thành công'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền catalog.edit'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: thiếu tên danh mục'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Tên danh mục không được để trống']], 422);
        }
        DB::table('categories')->where('id', $id)->update([
            'name' => $name,
            'description' => $request->input('description'),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $id, 'name' => $name]]);
    }

    #[OA\Delete(
        path: '/categories/{id}',
        summary: 'Xóa danh mục sản phẩm',
        description: 'Yêu cầu quyền catalog.manage. Không xóa được danh mục đang có sản phẩm.',
        tags: ['Danh mục'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền catalog.manage'),
            new OA\Response(response: 422, description: 'HAS_PRODUCTS: danh mục đang có sản phẩm liên kết'),
        ]
    )]
    public function destroy($id)
    {
        $hasProducts = DB::table('products')->where('category_id', $id)->exists();
        if ($hasProducts) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'HAS_PRODUCTS', 'message' => 'Không thể xóa danh mục đang có sản phẩm liên kết.'],
            ], 422);
        }
        DB::table('categories')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa danh mục thành công.']);
    }
}
