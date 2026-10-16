<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Messaging\RabbitMqPublisher;

class ProductController extends Controller
{
    private function hasSignedRole(Request $request, array $allowed): bool
    {
        $token = $request->bearerToken();
        if (!$token || count($parts = explode('.', $token)) !== 3) return false;
        [$header, $payload, $signature] = $parts;
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", env('JWT_SECRET', 'smart_erp_super_secure_jwt_secret_key_2026'), true)), '+/', '-_'), '=');
        if (!hash_equals($expected, $signature)) return false;
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        return is_array($claims) && ($claims['exp'] ?? 0) > time() && count(array_intersect($allowed, $claims['roles'] ?? [])) > 0;
    }

    private function canSeeCost(Request $request): bool
    {
        return $this->hasSignedRole($request, ['SUPER_ADMIN', 'PURCHASE', 'PURCHASE_MANAGER', 'FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER']);
    }
    public function applyInquiryResult(Request $request)
    {
        $key = env('INTERNAL_SERVICE_KEY', 'smart_erp_internal_2026');
        if (!hash_equals($key, (string) $request->header('X-Internal-Service-Key')) || !$this->hasSignedRole($request, ['SUPER_ADMIN', 'PURCHASE', 'PURCHASE_MANAGER'])) {
            return response()->json(['success' => false, 'error' => ['message' => 'Forbidden']], 403);
        }
        $data = $request->validate([
            'inquiry_item_id' => 'required|integer|min:1',
            'sku' => 'nullable|string|max:100',
            'designation' => 'required|string|max:255',
            'description' => 'nullable|string',
            'brand_id' => 'required|integer',
            'category' => 'nullable|string|max:200',
            'com' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'spec' => 'nullable|string',
            'buying_price' => 'required|numeric|min:0',
            'vat' => 'required|numeric|between:0,100',
            'tax' => 'required|numeric|between:0,100',
            'price_index' => 'required|numeric|gt:0',
        ]);
        return DB::transaction(function () use ($data) {
            $brand = DB::table('brands')->where('id', $data['brand_id'])->first();
            if (!$brand) return response()->json(['success' => false, 'error' => ['message' => 'Brand không tồn tại']], 422);
            $sku = trim($data['sku'] ?? '') ?: 'IQP-' . $data['inquiry_item_id'];
            $gross = round((float) $data['buying_price'] * (1 + ((float) $data['vat'] + (float) $data['tax']) / 100) * (float) $data['price_index'], 2);
            $categoryId = null;
            if (!empty($data['category'])) {
                $categoryId = DB::table('categories')->where('name', $data['category'])->value('id');
                if (!$categoryId) $categoryId = DB::table('categories')->insertGetId(['name' => $data['category'], 'code' => Str::slug($data['category']) . '-' . $data['inquiry_item_id'], 'created_at' => now(), 'updated_at' => now()]);
            }
            $changes = ['designation' => $data['designation'], 'brand_id' => $brand->id, 'category_id' => $categoryId, 'description' => $data['description'] ?? null,
                'com' => $data['com'] ?? null, 'unit' => $data['unit'] ?? 'Cái', 'spec' => $data['spec'] ?? null,
                'cost_price' => $data['buying_price'], 'gross_price' => $gross, 'status' => 'ACTIVE', 'updated_at' => now()];
            $existing = DB::table('products')->where('sku', $sku)->first();
            if ($existing) DB::table('products')->where('id', $existing->id)->update($changes);
            else {
                $changes['sku'] = $sku;
                $changes['min_stock'] = 0;
                $changes['created_at'] = now();
                $existing = (object) ['id' => DB::table('products')->insertGetId($changes)];
            }
            return response()->json(['success' => true, 'data' => ['id' => $existing->id, 'sku' => $sku, 'gross_price' => $gross]]);
        });
    }
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
        if (!$this->canSeeCost($request)) foreach ($products as $product) unset($product->cost_price);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function show(Request $request, $id)
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
        if (!$this->canSeeCost($request)) unset($p->cost_price);
        return response()->json(['success' => true, 'data' => $p]);
    }

    public function store(Request $request, RabbitMqPublisher $publisher)
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

        $designation = $request->input('designation') ?: $description;
        $origin = $request->input('com', 'Việt Nam');
        $unit = $request->input('unit', 'Cái');
        $minStock = (int) $request->input('min_stock', 0);

        $id = DB::table('products')->insertGetId([
            'sku' => $sku,
            'designation' => $designation,
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'description' => $description,
            'com' => $origin,
            'unit' => $unit,
            'gross_price' => $grossPrice,
            'spec' => $request->input('spec'),
            'cost_price' => (float) $request->input('cost_price', 0),
            'min_stock' => $minStock,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $publisher->publishProductCreated([
            'product_id' => $id,
            'sku' => $sku,
            'name' => $designation,
            'origin' => $origin,
            'unit' => $unit,
            'gross_price' => $grossPrice,
            'min_stock' => $minStock,
        ]);
        $publisher->close();

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
