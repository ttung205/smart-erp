<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class BrandController extends Controller
{
    #[OA\Get(
        path: '/brands',
        summary: 'Danh sách thương hiệu',
        description: 'Trường price_index chỉ trả về cho vai trò SUPER_ADMIN, PURCHASE, PURCHASE_MANAGER.',
        tags: ['Thương hiệu'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Thương hiệu kèm số sản phẩm (products_count)'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
        ]
    )]
    public function index(Request $request)
    {
        $brands = DB::table('brands')
            ->leftJoin('products', 'brands.id', '=', 'products.brand_id')
            ->select('brands.id', 'brands.name', 'brands.price_index', 'brands.created_at', DB::raw('COUNT(products.id) as products_count'))
            ->groupBy('brands.id', 'brands.name', 'brands.price_index', 'brands.created_at')
            ->orderBy('brands.name')
            ->get();
        if (! $this->canSeePriceIndex($request)) {
            foreach ($brands as $brand) {
                unset($brand->price_index);
            }
        }

        return response()->json(['success' => true, 'data' => $brands]);
    }

    #[OA\Get(
        path: '/brands/{id}',
        summary: 'Chi tiết thương hiệu',
        description: 'Trường price_index chỉ trả về cho vai trò SUPER_ADMIN, PURCHASE, PURCHASE_MANAGER.',
        tags: ['Thương hiệu'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Thông tin thương hiệu'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 404, description: 'Không tìm thấy thương hiệu'),
        ]
    )]
    public function show(Request $request, $id)
    {
        $brand = DB::table('brands')->where('id', $id)->first();
        if ($brand && ! $this->canSeePriceIndex($request)) {
            unset($brand->price_index);
        }

        return $brand ? response()->json(['success' => true, 'data' => $brand]) : response()->json(['success' => false], 404);
    }

    private function canSeePriceIndex(Request $request): bool
    {
        return $this->actor($request)->hasAnyRole('SUPER_ADMIN', 'PURCHASE', 'PURCHASE_MANAGER');
    }

    private function isSuperAdmin(Request $request): bool
    {
        return $this->actor($request)->hasAnyRole('SUPER_ADMIN');
    }

    #[OA\Post(
        path: '/brands',
        summary: 'Tạo thương hiệu',
        description: 'Yêu cầu quyền catalog.create. Chỉ SUPER_ADMIN được gửi price_index.',
        tags: ['Thương hiệu'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Gree'),
                    new OA\Property(property: 'price_index', type: 'number', nullable: true, exclusiveMinimum: 0, description: 'Hệ số giá bán, chỉ SUPER_ADMIN'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tạo thành công, trả về id và tên'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền catalog.create, hoặc không phải SUPER_ADMIN mà gửi price_index'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED, DUPLICATE (trùng tên) hoặc price_index không hợp lệ'),
        ]
    )]
    public function store(Request $request)
    {
        if ($request->has('price_index') && ! $this->isSuperAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['message' => 'Chỉ Super Admin được thiết lập price index.']], 403);
        }
        $index = $request->input('price_index');
        if ($index !== null && (! is_numeric($index) || $index <= 0)) {
            return response()->json(['success' => false, 'error' => ['message' => 'Price index phải lớn hơn 0.']], 422);
        }
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Tên thương hiệu không được để trống']], 422);
        }
        $exists = DB::table('brands')->where('name', $name)->first();
        if ($exists) {
            return response()->json(['success' => false, 'error' => ['code' => 'DUPLICATE', 'message' => 'Thương hiệu này đã tồn tại']], 422);
        }
        $id = DB::table('brands')->insertGetId([
            'name' => $name,
            'price_index' => $index,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $id, 'name' => $name]], 201);
    }

    #[OA\Put(
        path: '/brands/{id}',
        summary: 'Cập nhật thương hiệu',
        description: 'Yêu cầu quyền catalog.edit. Chấp nhận cả PUT và PATCH. Chỉ SUPER_ADMIN được gửi price_index.',
        tags: ['Thương hiệu'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'price_index', type: 'number', nullable: true, exclusiveMinimum: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật thành công'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền catalog.edit, hoặc không phải SUPER_ADMIN mà gửi price_index'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED hoặc price_index không hợp lệ'),
        ]
    )]
    public function update(Request $request, $id)
    {
        if ($request->has('price_index') && ! $this->isSuperAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['message' => 'Chỉ Super Admin được thiết lập price index.']], 403);
        }
        $index = $request->input('price_index');
        if ($request->has('price_index') && $index !== null && (! is_numeric($index) || $index <= 0)) {
            return response()->json(['success' => false, 'error' => ['message' => 'Price index phải lớn hơn 0.']], 422);
        }
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Tên thương hiệu không được để trống']], 422);
        }
        $changes = [
            'name' => $name,
            'updated_at' => now(),
        ];
        if ($request->has('price_index')) {
            $changes['price_index'] = $index;
        }
        DB::table('brands')->where('id', $id)->update($changes);

        return response()->json(['success' => true, 'data' => ['id' => $id, 'name' => $name]]);
    }

    #[OA\Delete(
        path: '/brands/{id}',
        summary: 'Xóa thương hiệu',
        description: 'Yêu cầu quyền catalog.manage. Không xóa được thương hiệu đang có sản phẩm.',
        tags: ['Thương hiệu'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền catalog.manage'),
            new OA\Response(response: 422, description: 'HAS_PRODUCTS: thương hiệu đang có sản phẩm liên kết'),
        ]
    )]
    public function destroy($id)
    {
        $hasProducts = DB::table('products')->where('brand_id', $id)->exists();
        if ($hasProducts) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'HAS_PRODUCTS', 'message' => 'Không thể xóa thương hiệu đang có sản phẩm liên kết.'],
            ], 422);
        }
        DB::table('brands')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa thương hiệu thành công.']);
    }
}
