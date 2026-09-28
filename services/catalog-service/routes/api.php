<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'data' => [
            'service' => 'catalog-service',
            'framework' => 'Laravel 11 / 12',
            'status' => 'UP',
            'timestamp' => now()->toIso8601String(),
        ],
    ]);
});

// ==========================================
// 1. PRODUCTS CRUD & IMPORT
// ==========================================

// Danh sách sản phẩm
Route::get('/products', function (Request $request) {
    $search = trim($request->input('search', ''));
    $catId = $request->input('category_id');
    $brandId = $request->input('brand_id');

    $query = DB::table('products')
        ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
        ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
        ->select(
            'products.id',
            'products.sku',
            'products.designation',
            'brands.name as brand',
            'categories.name as category',
            'products.category_id',
            'products.brand_id',
            'products.description as item_description',
            'products.description',
            'products.com',
            'products.unit',
            'products.gross_price',
            'products.spec',
            'products.cost_price',
            'products.min_stock',
            'products.status',
            'products.created_at',
            'products.updated_at'
        )
        ->where('products.status', 'ACTIVE');

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('products.sku', 'like', "%{$search}%")
              ->orWhere('products.designation', 'like', "%{$search}%")
              ->orWhere('products.description', 'like', "%{$search}%")
              ->orWhere('brands.name', 'like', "%{$search}%")
              ->orWhere('categories.name', 'like', "%{$search}%");
        });
    }

    if ($catId) {
        $query->where('products.category_id', $catId);
    }

    if ($brandId) {
        $query->where('products.brand_id', $brandId);
    }

    $products = $query->orderBy('products.id', 'desc')->get();

    return response()->json(['success' => true, 'data' => $products]);
});

// Chi tiết 1 sản phẩm
Route::get('/products/{id}', function ($id) {
    $p = DB::table('products')
        ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
        ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
        ->select(
            'products.*',
            'brands.name as brand',
            'categories.name as category'
        )
        ->where('products.id', $id)
        ->first();

    if (!$p) {
        return response()->json(['success' => false, 'error' => ['code' => 'PRODUCT_NOT_FOUND']], 404);
    }
    return response()->json(['success' => true, 'data' => $p]);
});

// Import hàng loạt sản phẩm từ Excel / JSON
Route::post('/products/import', function (Request $request) {
    $items = $request->input('items', []);

    if (empty($items) || !is_array($items)) {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'EMPTY_DATA', 'message' => 'Dữ liệu sản phẩm rỗng hoặc không đúng định dạng.']
        ], 422);
    }

    $importedCount = 0;
    $errors = [];

    DB::beginTransaction();
    try {
        foreach ($items as $index => $row) {
            $sku = trim($row['sku'] ?? $row['SKU'] ?? '');
            if (empty($sku)) {
                $errors[] = "Dòng " . ($index + 1) . ": Thiếu mã SKU";
                continue;
            }

            // Xử lý Brand
            $brandName = trim($row['brand'] ?? $row['Brand'] ?? '');
            $brandId = null;
            if (!empty($brandName)) {
                $brand = DB::table('brands')->where('name', $brandName)->first();
                if (!$brand) {
                    $brandId = DB::table('brands')->insertGetId([
                        'name' => $brandName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $brandId = $brand->id;
                }
            }

            // Xử lý Category
            $categoryName = trim($row['category'] ?? $row['Category'] ?? '');
            $categoryId = null;
            if (!empty($categoryName)) {
                $cat = DB::table('categories')->where('name', $categoryName)->first();
                if (!$cat) {
                    $categoryId = DB::table('categories')->insertGetId([
                        'code' => Str::slug($categoryName) . '-' . rand(100, 999),
                        'name' => $categoryName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $categoryId = $cat->id;
                }
            }

            $designation = trim($row['designation'] ?? $row['Designation'] ?? '');
            $description = trim($row['item_description'] ?? $row['item description'] ?? $row['description'] ?? '');
            $com = trim($row['com'] ?? $row['COM'] ?? 'Việt Nam');
            $unit = trim($row['unit'] ?? $row['Unit'] ?? 'Cái');
            $grossPrice = (float)($row['gross_price'] ?? $row['gross price'] ?? $row['price'] ?? 0);
            $spec = trim($row['specs'] ?? $row['spec'] ?? $row['Spec'] ?? '');

            $exists = DB::table('products')->where('sku', $sku)->first();
            $data = [
                'sku' => $sku,
                'designation' => $designation,
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'description' => $description,
                'com' => $com ?: 'Việt Nam',
                'unit' => $unit ?: 'Cái',
                'gross_price' => $grossPrice,
                'spec' => $spec,
                'status' => 'ACTIVE',
                'updated_at' => now(),
            ];

            if ($exists) {
                DB::table('products')->where('sku', $sku)->update($data);
            } else {
                $data['created_at'] = now();
                DB::table('products')->insert($data);
            }

            $importedCount++;
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'data' => [
                'imported_count' => $importedCount,
                'errors' => $errors,
                'message' => "Đã import thành công {$importedCount} sản phẩm vào cơ sở dữ liệu."
            ]
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'error' => ['code' => 'IMPORT_FAILED', 'message' => $e->getMessage()]
        ], 500);
    }
});

// Thêm mới sản phẩm lẻ
Route::post('/products', function (Request $request) {
    $sku = trim($request->input('sku', ''));
    $grossPrice = (float) $request->input('gross_price', 0);
    $description = trim($request->input('item_description', $request->input('description', '')));

    if (empty($sku)) {
        return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'SKU không được để trống']], 422);
    }

    $brandName = trim($request->input('brand', ''));
    $brandId = $request->input('brand_id');
    if (!$brandId && !empty($brandName)) {
        $brand = DB::table('brands')->where('name', $brandName)->first();
        $brandId = $brand ? $brand->id : DB::table('brands')->insertGetId(['name' => $brandName, 'created_at' => now(), 'updated_at' => now()]);
    }

    $categoryName = trim($request->input('category', ''));
    $categoryId = $request->input('category_id');
    if (!$categoryId && !empty($categoryName)) {
        $cat = DB::table('categories')->where('name', $categoryName)->first();
        $categoryId = $cat ? $cat->id : DB::table('categories')->insertGetId(['name' => $categoryName, 'code' => Str::slug($categoryName) . '-' . rand(100, 999), 'created_at' => now(), 'updated_at' => now()]);
    }

    $id = DB::table('products')->insertGetId([
        'sku' => $sku,
        'designation' => $request->input('designation'),
        'brand_id' => $brandId,
        'category_id' => $categoryId,
        'description' => $description,
        'com' => $request->input('com', 'Việt Nam'),
        'unit' => $request->input('unit', 'Cái'),
        'gross_price' => $grossPrice,
        'spec' => $request->input('spec'),
        'cost_price' => (float) $request->input('cost_price', 0),
        'min_stock' => (int) $request->input('min_stock', 0),
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json(['success' => true, 'data' => ['id' => $id, 'sku' => $sku]], 201);
});

// Chỉnh sửa sản phẩm
Route::put('/products/{id}', function (Request $request, $id) {
    $p = DB::table('products')->where('id', $id)->first();
    if (!$p) {
        return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
    }

    $brandName = trim($request->input('brand', ''));
    $brandId = $request->input('brand_id');
    if (!$brandId && !empty($brandName)) {
        $brand = DB::table('brands')->where('name', $brandName)->first();
        $brandId = $brand ? $brand->id : DB::table('brands')->insertGetId(['name' => $brandName, 'created_at' => now(), 'updated_at' => now()]);
    }

    $categoryName = trim($request->input('category', ''));
    $categoryId = $request->input('category_id');
    if (!$categoryId && !empty($categoryName)) {
        $cat = DB::table('categories')->where('name', $categoryName)->first();
        $categoryId = $cat ? $cat->id : DB::table('categories')->insertGetId(['name' => $categoryName, 'code' => Str::slug($categoryName) . '-' . rand(100, 999), 'created_at' => now(), 'updated_at' => now()]);
    }

    $updateData = [
        'designation' => $request->input('designation', $p->designation),
        'description' => $request->input('description', $request->input('item_description', $p->description)),
        'com' => $request->input('com', $p->com),
        'unit' => $request->input('unit', $p->unit),
        'gross_price' => (float) $request->input('gross_price', $p->gross_price),
        'spec' => $request->input('spec', $p->spec),
        'updated_at' => now(),
    ];

    if ($brandId) $updateData['brand_id'] = $brandId;
    if ($categoryId) $updateData['category_id'] = $categoryId;
    if ($request->has('sku')) $updateData['sku'] = trim($request->input('sku'));

    DB::table('products')->where('id', $id)->update($updateData);

    return response()->json(['success' => true, 'data' => ['id' => $id]]);
});

// Xóa sản phẩm
Route::delete('/products/{id}', function ($id) {
    $deleted = DB::table('products')->where('id', $id)->delete();
    if (!$deleted) {
        return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
    }
    return response()->json(['success' => true, 'message' => 'Đã xóa sản phẩm thành công.']);
});

// ==========================================
// 2. BRANDS CRUD
// ==========================================

Route::get('/brands', function () {
    $brands = DB::table('brands')
        ->leftJoin('products', 'brands.id', '=', 'products.brand_id')
        ->select('brands.id', 'brands.name', 'brands.created_at', DB::raw('COUNT(products.id) as products_count'))
        ->groupBy('brands.id', 'brands.name', 'brands.created_at')
        ->orderBy('brands.name')
        ->get();
    return response()->json(['success' => true, 'data' => $brands]);
});

Route::post('/brands', function (Request $request) {
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
});

Route::put('/brands/{id}', function (Request $request, $id) {
    $name = trim($request->input('name', ''));
    if (empty($name)) {
        return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Tên thương hiệu không được để trống']], 422);
    }
    DB::table('brands')->where('id', $id)->update([
        'name' => $name,
        'updated_at' => now(),
    ]);
    return response()->json(['success' => true, 'data' => ['id' => $id, 'name' => $name]]);
});

Route::delete('/brands/{id}', function ($id) {
    $hasProducts = DB::table('products')->where('brand_id', $id)->exists();
    if ($hasProducts) {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'HAS_PRODUCTS', 'message' => 'Không thể xóa thương hiệu đang có sản phẩm liên kết.']
        ], 422);
    }
    DB::table('brands')->where('id', $id)->delete();
    return response()->json(['success' => true, 'message' => 'Đã xóa thương hiệu thành công.']);
});

// ==========================================
// 3. CATEGORIES CRUD
// ==========================================

Route::get('/categories', function () {
    $cats = DB::table('categories')
        ->leftJoin('products', 'categories.id', '=', 'products.category_id')
        ->select('categories.id', 'categories.code', 'categories.name', 'categories.description', 'categories.created_at', DB::raw('COUNT(products.id) as products_count'))
        ->groupBy('categories.id', 'categories.code', 'categories.name', 'categories.description', 'categories.created_at')
        ->orderBy('categories.name')
        ->get();
    return response()->json(['success' => true, 'data' => $cats]);
});

Route::post('/categories', function (Request $request) {
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
});

Route::put('/categories/{id}', function (Request $request, $id) {
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
});

Route::delete('/categories/{id}', function ($id) {
    $hasProducts = DB::table('products')->where('category_id', $id)->exists();
    if ($hasProducts) {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'HAS_PRODUCTS', 'message' => 'Không thể xóa danh mục đang có sản phẩm liên kết.']
        ], 422);
    }
    DB::table('categories')->where('id', $id)->delete();
    return response()->json(['success' => true, 'message' => 'Đã xóa danh mục thành công.']);
});

// ==========================================
// 4. PARTNERS & WAREHOUSES
// ==========================================
Route::get('/partners', function (Request $request) {
    $type = $request->input('type');
    $query = DB::table('partners');
    if ($type) {
        $query->where('type', strtoupper($type));
    }
    return response()->json(['success' => true, 'data' => $query->get()]);
});

Route::get('/warehouses', function () {
    return response()->json(['success' => true, 'data' => DB::table('warehouses')->where('is_active', 1)->get()]);
});