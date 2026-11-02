<?php

namespace App\Http\Controllers;

use App\Support\ProductEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProductInput',
    properties: [
        new OA\Property(property: 'sku', type: 'string', example: 'GDN12BC-K5EBA2B-GRE'),
        new OA\Property(property: 'designation', type: 'string', description: 'Mặc định lấy theo mô tả'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'item_description', type: 'string', nullable: true, description: 'Tên khác của description'),
        new OA\Property(property: 'brand_id', type: 'integer', nullable: true),
        new OA\Property(property: 'brand', type: 'string', nullable: true, description: 'Tên thương hiệu; tự tạo nếu chưa có và không gửi brand_id'),
        new OA\Property(property: 'category_id', type: 'integer', nullable: true),
        new OA\Property(property: 'category', type: 'string', nullable: true, description: 'Tên danh mục; tự tạo nếu chưa có và không gửi category_id'),
        new OA\Property(property: 'com', type: 'string', default: 'Việt Nam', description: 'Xuất xứ'),
        new OA\Property(property: 'unit', type: 'string', default: 'Cái'),
        new OA\Property(property: 'gross_price', type: 'number', default: 0),
        new OA\Property(property: 'cost_price', type: 'number', default: 0),
        new OA\Property(property: 'spec', type: 'string', nullable: true),
        new OA\Property(property: 'min_stock', type: 'integer', default: 0),
    ],
    type: 'object'
)]
class ProductController extends Controller
{
    /** @return list<string> Tham số dạng "1,2,3" hoặc mảng ids[]=1&ids[]=2 */
    private static function listParam(Request $request, string $key): array
    {
        $value = $request->input($key);
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', $items), fn ($v) => $v !== ''));
    }

    private function canSeeCost(Request $request): bool
    {
        return $this->actor($request)->hasAnyRole('SUPER_ADMIN', 'PURCHASE', 'PURCHASE_MANAGER', 'FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER');
    }

    // Ghi lịch sử khi gross/cost price thay đổi ($old = bản ghi trước khi sửa, null nếu tạo mới)
    private function recordPriceChange(Request $request, int $productId, ?object $old, float $newGross, ?float $newCost, string $source, ?string $reference = null): void
    {
        $oldGross = $old ? (float) $old->gross_price : null;
        $oldCost = $old && isset($old->cost_price) ? (float) $old->cost_price : null;
        $costChanged = $newCost !== null && abs(($oldCost ?? 0) - $newCost) >= 0.005;
        if ($old && abs($oldGross - $newGross) < 0.005 && ! $costChanged) {
            return;
        }
        $actor = $this->actor($request);
        DB::table('product_price_histories')->insert([
            'product_id' => $productId,
            'old_gross_price' => $oldGross,
            'new_gross_price' => $newGross,
            'old_cost_price' => $oldCost,
            'new_cost_price' => $newCost ?? $oldCost,
            'source' => $source,
            'reference' => $reference,
            'changed_by' => $actor?->id,
            'changed_by_name' => $actor?->name,
            'created_at' => now(),
        ]);
    }

    #[OA\Get(
        path: '/products/price-history',
        summary: 'Lịch sử thay đổi giá sản phẩm',
        description: 'Tìm theo product_id hoặc sku. Giá vốn chỉ trả về cho SUPER_ADMIN, PURCHASE, PURCHASE_MANAGER, FINANCE_ACCOUNTING, FINANCE_ACCOUNTING_MANAGER.',
        tags: ['Sản phẩm'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sku', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Thông tin sản phẩm và các lần đổi giá, mới nhất trước'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 404, description: 'PRODUCT_NOT_FOUND'),
        ]
    )]
    public function priceHistory(Request $request)
    {
        $sku = trim($request->input('sku', ''));
        $productId = $request->input('product_id');
        $product = $productId ? DB::table('products')->where('id', $productId)->first() : ($sku !== '' ? DB::table('products')->where('sku', $sku)->first() : null);
        if (! $product) {
            return response()->json(['success' => false, 'error' => ['code' => 'PRODUCT_NOT_FOUND', 'message' => 'Không tìm thấy sản phẩm.']], 404);
        }
        $histories = DB::table('product_price_histories')->where('product_id', $product->id)->orderByDesc('id')->get();
        if (! $this->canSeeCost($request)) {
            $histories->each(function ($h) {
                unset($h->old_cost_price, $h->new_cost_price);
            });
        }

        return response()->json(['success' => true, 'data' => [
            'product' => ['id' => $product->id, 'sku' => $product->sku, 'designation' => $product->designation, 'gross_price' => $product->gross_price],
            'histories' => $histories,
        ]]);
    }

    #[OA\Post(
        path: '/products/apply-inquiry-result',
        summary: 'Áp kết quả hỏi hàng vào sản phẩm (nội bộ)',
        description: 'Chỉ purchase-service gọi: cần cả JWT của người thao tác (vai trò SUPER_ADMIN, PURCHASE hoặc PURCHASE_MANAGER) và khóa nội bộ. '
            .'Giá bán = giá mua x (1 + (vat + tax)/100) x price_index. Tạo mới hoặc cập nhật theo SKU (mặc định IQP-{inquiry_item_id}).',
        tags: ['Sản phẩm'],
        security: [['bearerAuth' => [], 'internalServiceKey' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['inquiry_item_id', 'designation', 'brand_id', 'buying_price', 'vat', 'tax', 'price_index'],
                properties: [
                    new OA\Property(property: 'inquiry_item_id', type: 'integer', minimum: 1),
                    new OA\Property(property: 'sku', type: 'string', nullable: true, maxLength: 100),
                    new OA\Property(property: 'designation', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'brand_id', type: 'integer'),
                    new OA\Property(property: 'category', type: 'string', nullable: true, maxLength: 200),
                    new OA\Property(property: 'com', type: 'string', nullable: true, maxLength: 100),
                    new OA\Property(property: 'unit', type: 'string', nullable: true, maxLength: 50),
                    new OA\Property(property: 'spec', type: 'string', nullable: true),
                    new OA\Property(property: 'buying_price', type: 'number', minimum: 0),
                    new OA\Property(property: 'vat', type: 'number', maximum: 100, minimum: 0),
                    new OA\Property(property: 'tax', type: 'number', maximum: 100, minimum: 0),
                    new OA\Property(property: 'price_index', type: 'number', exclusiveMinimum: 0),
                    new OA\Property(property: 'reference', type: 'string', nullable: true, maxLength: 100),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'id, sku và giá bán sau khi áp'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu khóa nội bộ hoặc người thao tác không thuộc bộ phận mua hàng'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED hoặc thương hiệu không tồn tại'),
        ]
    )]
    public function applyInquiryResult(Request $request)
    {
        // Khóa nội bộ đã được middleware internal kiểm tra; ở đây chỉ còn kiểm tra người thao tác bên purchase
        if (! $this->actor($request)->hasAnyRole('SUPER_ADMIN', 'PURCHASE', 'PURCHASE_MANAGER')) {
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
            'reference' => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $brand = DB::table('brands')->where('id', $data['brand_id'])->first();
            if (! $brand) {
                return response()->json(['success' => false, 'error' => ['message' => 'Brand không tồn tại']], 422);
            }
            $sku = trim($data['sku'] ?? '') ?: 'IQP-'.$data['inquiry_item_id'];
            $gross = round((float) $data['buying_price'] * (1 + ((float) $data['vat'] + (float) $data['tax']) / 100) * (float) $data['price_index'], 2);
            $categoryId = null;
            if (! empty($data['category'])) {
                $categoryId = DB::table('categories')->where('name', $data['category'])->value('id');
                if (! $categoryId) {
                    $categoryId = DB::table('categories')->insertGetId(['name' => $data['category'], 'code' => Str::slug($data['category']).'-'.$data['inquiry_item_id'], 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $changes = ['designation' => $data['designation'], 'brand_id' => $brand->id, 'category_id' => $categoryId, 'description' => $data['description'] ?? null,
                'com' => $data['com'] ?? null, 'unit' => $data['unit'] ?? 'Cái', 'spec' => $data['spec'] ?? null,
                'cost_price' => $data['buying_price'], 'gross_price' => $gross, 'status' => 'ACTIVE', 'updated_at' => now()];
            $existing = DB::table('products')->where('sku', $sku)->first();
            if ($existing) {
                DB::table('products')->where('id', $existing->id)->update($changes);
                $this->recordPriceChange($request, $existing->id, $existing, $gross, (float) $data['buying_price'], 'INQUIRY', $data['reference'] ?? null);
            } else {
                $changes['sku'] = $sku;
                $changes['min_stock'] = 0;
                $changes['created_at'] = now();
                $existing = (object) ['id' => DB::table('products')->insertGetId($changes)];
                $this->recordPriceChange($request, $existing->id, null, $gross, (float) $data['buying_price'], 'INQUIRY', $data['reference'] ?? null);
                ProductEvents::created($existing->id);
            }

            return response()->json(['success' => true, 'data' => ['id' => $existing->id, 'sku' => $sku, 'gross_price' => $gross]]);
        });
    }

    #[OA\Get(
        path: '/products',
        summary: 'Danh sách sản phẩm',
        description: 'Gọi bằng JWT hoặc khóa nội bộ. Mặc định chỉ trả sản phẩm ACTIVE; tra cứu theo ids hoặc skus thì trả cả sản phẩm đã ngừng bán (để snapshot chứng từ cũ). '
            .'Giá vốn (cost_price) chỉ trả về cho SUPER_ADMIN, PURCHASE, PURCHASE_MANAGER, FINANCE_ACCOUNTING, FINANCE_ACCOUNTING_MANAGER.',
        tags: ['Sản phẩm'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Tìm theo SKU, model, mô tả, thương hiệu hoặc danh mục', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'brand_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'ids', in: 'query', required: false, description: 'Danh sách id phân tách bằng dấu phẩy', schema: new OA\Schema(type: 'string', example: '1,2,3')),
            new OA\Parameter(name: 'skus', in: 'query', required: false, description: 'Danh sách SKU phân tách bằng dấu phẩy', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách sản phẩm kèm tên thương hiệu và danh mục, mới nhất trước'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu token hoặc khóa nội bộ'),
        ]
    )]
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
            );

        // Tra cứu batch theo ids/skus (service khác snapshot chứng từ cũ) lấy cả sản phẩm đã ngừng bán
        $ids = self::listParam($request, 'ids');
        $skus = self::listParam($request, 'skus');
        if ($ids || $skus) {
            $query->where(function ($q) use ($ids, $skus) {
                if ($ids) {
                    $q->whereIn('products.id', array_map('intval', $ids));
                }
                if ($skus) {
                    $q->orWhereIn('products.sku', $skus);
                }
            });
        } else {
            $query->where('products.status', 'ACTIVE');
        }

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
        if (! $this->canSeeCost($request)) {
            foreach ($products as $product) {
                unset($product->cost_price);
            }
        }

        return response()->json(['success' => true, 'data' => $products]);
    }

    #[OA\Get(
        path: '/products/{id}',
        summary: 'Chi tiết sản phẩm',
        description: 'Giá vốn (cost_price) chỉ trả về cho các vai trò mua hàng và kế toán.',
        tags: ['Sản phẩm'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Thông tin sản phẩm'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 404, description: 'PRODUCT_NOT_FOUND'),
        ]
    )]
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

        if (! $p) {
            return response()->json(['success' => false, 'error' => ['code' => 'PRODUCT_NOT_FOUND']], 404);
        }
        if (! $this->canSeeCost($request)) {
            unset($p->cost_price);
        }

        return response()->json(['success' => true, 'data' => $p]);
    }

    #[OA\Post(
        path: '/products',
        summary: 'Tạo sản phẩm',
        description: 'Yêu cầu quyền catalog.create. Bắt buộc có sku. Ghi lịch sử giá và phát event ProductCreated qua outbox trong cùng transaction.',
        tags: ['Sản phẩm'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput')),
        responses: [
            new OA\Response(response: 201, description: 'id và sku của sản phẩm mới'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền catalog.create'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: thiếu SKU'),
        ]
    )]
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
        if (! $brandId && ! empty($brandName)) {
            $brand = DB::table('brands')->where('name', $brandName)->first();
            $brandId = $brand ? $brand->id : DB::table('brands')->insertGetId(['name' => $brandName, 'created_at' => now(), 'updated_at' => now()]);
        }

        $categoryName = trim($request->input('category', ''));
        $categoryId = $request->input('category_id');
        if (! $categoryId && ! empty($categoryName)) {
            $cat = DB::table('categories')->where('name', $categoryName)->first();
            $categoryId = $cat ? $cat->id : DB::table('categories')->insertGetId(['name' => $categoryName, 'code' => Str::slug($categoryName).'-'.rand(100, 999), 'created_at' => now(), 'updated_at' => now()]);
        }

        $designation = $request->input('designation') ?: $description;
        $origin = $request->input('com', 'Việt Nam');
        $unit = $request->input('unit', 'Cái');
        $minStock = (int) $request->input('min_stock', 0);

        // Sản phẩm + lịch sử giá + event ProductCreated trong cùng một transaction (transactional outbox)
        $id = DB::transaction(function () use ($request, $sku, $designation, $brandId, $categoryId, $description, $origin, $unit, $grossPrice, $minStock) {
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
            $this->recordPriceChange($request, $id, null, $grossPrice, (float) $request->input('cost_price', 0), 'CREATE');
            ProductEvents::created($id);

            return $id;
        });

        return response()->json(['success' => true, 'data' => ['id' => $id, 'sku' => $sku]], 201);
    }

    #[OA\Put(
        path: '/products/{id}',
        summary: 'Cập nhật sản phẩm',
        description: 'Yêu cầu quyền catalog.edit. Chấp nhận cả PUT và PATCH; trường không gửi giữ giá trị cũ. Đổi gross_price sẽ ghi lịch sử giá. Không cập nhật cost_price và min_stock.',
        tags: ['Sản phẩm'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput')),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật thành công'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền catalog.edit'),
            new OA\Response(response: 404, description: 'NOT_FOUND: không tìm thấy sản phẩm'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $p = DB::table('products')->where('id', $id)->first();
        if (! $p) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $brandName = trim($request->input('brand', ''));
        $brandId = $request->input('brand_id');
        if (! $brandId && ! empty($brandName)) {
            $brand = DB::table('brands')->where('name', $brandName)->first();
            $brandId = $brand ? $brand->id : DB::table('brands')->insertGetId(['name' => $brandName, 'created_at' => now(), 'updated_at' => now()]);
        }

        $categoryName = trim($request->input('category', ''));
        $categoryId = $request->input('category_id');
        if (! $categoryId && ! empty($categoryName)) {
            $cat = DB::table('categories')->where('name', $categoryName)->first();
            $categoryId = $cat ? $cat->id : DB::table('categories')->insertGetId(['name' => $categoryName, 'code' => Str::slug($categoryName).'-'.rand(100, 999), 'created_at' => now(), 'updated_at' => now()]);
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

        if ($brandId) {
            $updateData['brand_id'] = $brandId;
        }
        if ($categoryId) {
            $updateData['category_id'] = $categoryId;
        }
        if ($request->has('sku')) {
            $updateData['sku'] = trim($request->input('sku'));
        }

        DB::table('products')->where('id', $id)->update($updateData);
        $this->recordPriceChange($request, (int) $id, $p, $updateData['gross_price'], null, 'MANUAL');

        return response()->json(['success' => true, 'data' => ['id' => $id]]);
    }

    #[OA\Delete(
        path: '/products/{id}',
        summary: 'Xóa sản phẩm',
        description: 'Yêu cầu quyền catalog.manage.',
        tags: ['Sản phẩm'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền catalog.manage'),
            new OA\Response(response: 404, description: 'NOT_FOUND: không tìm thấy sản phẩm'),
        ]
    )]
    public function destroy($id)
    {
        $deleted = DB::table('products')->where('id', $id)->delete();
        if (! $deleted) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        return response()->json(['success' => true, 'message' => 'Đã xóa sản phẩm thành công.']);
    }

    #[OA\Post(
        path: '/products/import',
        summary: 'Nhập danh sách sản phẩm (import Excel)',
        description: 'Yêu cầu quyền catalog.create. Tạo mới hoặc cập nhật theo SKU trong một transaction; dòng thiếu SKU bị bỏ qua và báo trong errors. '
            .'Mỗi dòng nhận cả tên cột viết thường lẫn tiêu đề Excel (SKU, Brand, Category, Designation, gross price...).',
        tags: ['Sản phẩm'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['items'],
                properties: [
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'sku', type: 'string'),
                                new OA\Property(property: 'designation', type: 'string'),
                                new OA\Property(property: 'brand', type: 'string'),
                                new OA\Property(property: 'category', type: 'string'),
                                new OA\Property(property: 'item_description', type: 'string'),
                                new OA\Property(property: 'com', type: 'string'),
                                new OA\Property(property: 'unit', type: 'string'),
                                new OA\Property(property: 'gross_price', type: 'number'),
                                new OA\Property(property: 'spec', type: 'string'),
                            ],
                            type: 'object'
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Số sản phẩm đã nhập và danh sách lỗi theo dòng'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền catalog.create'),
            new OA\Response(response: 422, description: 'EMPTY_DATA: danh sách rỗng hoặc sai định dạng'),
            new OA\Response(response: 500, description: 'IMPORT_FAILED: lỗi khi ghi, đã rollback toàn bộ'),
        ]
    )]
    public function import(Request $request)
    {
        $items = $request->input('items', []);

        if (empty($items) || ! is_array($items)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'EMPTY_DATA', 'message' => 'Dữ liệu sản phẩm rỗng hoặc không đúng định dạng.'],
            ], 422);
        }

        $importedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($items as $index => $row) {
                $sku = trim($row['sku'] ?? $row['SKU'] ?? '');
                if (empty($sku)) {
                    $errors[] = 'Dòng '.($index + 1).': Thiếu mã SKU';

                    continue;
                }

                $brandName = trim($row['brand'] ?? $row['Brand'] ?? '');
                $brandId = null;
                if (! empty($brandName)) {
                    $brand = DB::table('brands')->where('name', $brandName)->first();
                    if (! $brand) {
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
                if (! empty($categoryName)) {
                    $cat = DB::table('categories')->where('name', $categoryName)->first();
                    if (! $cat) {
                        $categoryId = DB::table('categories')->insertGetId([
                            'code' => Str::slug($categoryName).'-'.rand(100, 999),
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
                $grossPrice = (float) ($row['gross_price'] ?? $row['gross price'] ?? $row['price'] ?? 0);
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
                    $this->recordPriceChange($request, $exists->id, $exists, $grossPrice, null, 'IMPORT');
                } else {
                    $data['created_at'] = now();
                    $newId = DB::table('products')->insertGetId($data);
                    $this->recordPriceChange($request, $newId, null, $grossPrice, null, 'IMPORT');
                    ProductEvents::created($newId);
                }

                $importedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'imported_count' => $importedCount,
                    'errors' => $errors,
                    'message' => "Đã import thành công {$importedCount} sản phẩm vào cơ sở dữ liệu.",
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => ['code' => 'IMPORT_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }
}
