<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
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
    }

    public function show($id)
    {
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
    }

    public function store(Request $request)
    {
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
    }

    public function update(Request $request, $id)
    {
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
    }

    public function destroy($id)
    {
        $deleted = DB::table('products')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }
        return response()->json(['success' => true, 'message' => 'Đã xóa sản phẩm thành công.']);
    }

    public function import(Request $request)
    {
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
    }
}
