<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
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

    public function store(Request $request)
    {
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Tên danh mục không được để trống']], 422);
        }
        $code = trim($request->input('code', '')) ?: Str::slug($name);
        $id = DB::table('categories')->insertGetId([
            'name' => $name,
            'code' => $code . '-' . rand(100, 999),
            'description' => $request->input('description'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'data' => ['id' => $id, 'name' => $name]], 201);
    }

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

    public function destroy($id)
    {
        $hasProducts = DB::table('products')->where('category_id', $id)->exists();
        if ($hasProducts) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'HAS_PRODUCTS', 'message' => 'Không thể xóa danh mục đang có sản phẩm liên kết.']
            ], 422);
        }
        DB::table('categories')->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa danh mục thành công.']);
    }
}
