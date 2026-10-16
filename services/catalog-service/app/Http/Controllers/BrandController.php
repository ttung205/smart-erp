<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $brands = DB::table('brands')
            ->leftJoin('products', 'brands.id', '=', 'products.brand_id')
            ->select('brands.id', 'brands.name', 'brands.price_index', 'brands.created_at', DB::raw('COUNT(products.id) as products_count'))
            ->groupBy('brands.id', 'brands.name', 'brands.price_index', 'brands.created_at')
            ->orderBy('brands.name')
            ->get();
        if (!$this->canSeePriceIndex($request)) {
            foreach ($brands as $brand) unset($brand->price_index);
        }
        return response()->json(['success' => true, 'data' => $brands]);
    }

    public function show(Request $request, $id)
    {
        $brand = DB::table('brands')->where('id', $id)->first();
        if ($brand && !$this->canSeePriceIndex($request)) unset($brand->price_index);
        return $brand ? response()->json(['success' => true, 'data' => $brand]) : response()->json(['success' => false], 404);
    }

    private function canSeePriceIndex(Request $request): bool
    {
        return $this->actor($request)->hasAnyRole('SUPER_ADMIN', 'PURCHASE', 'PURCHASE_MANAGER');
    }

    private function isSuperAdmin(Request $request): bool { return $this->actor($request)->hasAnyRole('SUPER_ADMIN'); }

    public function store(Request $request)
    {
        if ($request->has('price_index') && !$this->isSuperAdmin($request)) return response()->json(['success' => false, 'error' => ['message' => 'Chỉ Super Admin được thiết lập price index.']], 403);
        $index = $request->input('price_index');
        if ($index !== null && (!is_numeric($index) || $index <= 0)) return response()->json(['success' => false, 'error' => ['message' => 'Price index phải lớn hơn 0.']], 422);
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

    public function update(Request $request, $id)
    {
        if ($request->has('price_index') && !$this->isSuperAdmin($request)) return response()->json(['success' => false, 'error' => ['message' => 'Chỉ Super Admin được thiết lập price index.']], 403);
        $index = $request->input('price_index');
        if ($request->has('price_index') && $index !== null && (!is_numeric($index) || $index <= 0)) return response()->json(['success' => false, 'error' => ['message' => 'Price index phải lớn hơn 0.']], 422);
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Tên thương hiệu không được để trống']], 422);
        }
        $changes = [
            'name' => $name,
            'updated_at' => now(),
        ];
        if ($request->has('price_index')) $changes['price_index'] = $index;
        DB::table('brands')->where('id', $id)->update($changes);
        return response()->json(['success' => true, 'data' => ['id' => $id, 'name' => $name]]);
    }

    public function destroy($id)
    {
        $hasProducts = DB::table('products')->where('brand_id', $id)->exists();
        if ($hasProducts) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'HAS_PRODUCTS', 'message' => 'Không thể xóa thương hiệu đang có sản phẩm liên kết.']
            ], 422);
        }
        DB::table('brands')->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa thương hiệu thành công.']);
    }
}
