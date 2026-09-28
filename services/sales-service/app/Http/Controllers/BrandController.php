<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    public function index()
    {
        $brands = DB::table('brands')
            ->leftJoin('products', 'brands.id', '=', 'products.brand_id')
            ->select('brands.id', 'brands.name', 'brands.created_at', DB::raw('COUNT(products.id) as products_count'))
            ->groupBy('brands.id', 'brands.name', 'brands.created_at')
            ->orderBy('brands.name')
            ->get();
        return response()->json(['success' => true, 'data' => $brands]);
    }

    public function store(Request $request)
    {
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'data' => ['id' => $id, 'name' => $name]], 201);
    }

    public function update(Request $request, $id)
    {
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Tên thương hiệu không được để trống']], 422);
        }
        DB::table('brands')->where('id', $id)->update([
            'name' => $name,
            'updated_at' => now(),
        ]);
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
