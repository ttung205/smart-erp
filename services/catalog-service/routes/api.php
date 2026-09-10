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

Route::get('/products', function (Request $request) {
    $search = trim($request->input('search', ''));
    $catId = $request->input('category_id');

    $query = DB::table('products')
        ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
        ->leftJoin('units', 'products.unit_id', '=', 'units.id')
        ->select('products.id', 'products.sku', 'products.barcode', 'products.name', 'categories.name as category_name', 'products.gross_price', 'products.cost_price', 'products.min_stock', 'units.code as unit_code')
        ->where('products.status', 'ACTIVE');

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('products.sku', 'like', "%{$search}%")
              ->orWhere('products.name', 'like', "%{$search}%");
        });
    }

    if ($catId) {
        $query->where('products.category_id', $catId);
    }

    return response()->json(['success' => true, 'data' => $query->get()]);
});

Route::get('/products/{id}', function ($id) {
    $p = DB::table('products')->where('id', $id)->first();
    if (!$p) {
        return response()->json(['success' => false, 'error' => ['code' => 'PRODUCT_NOT_FOUND']], 404);
    }
    return response()->json(['success' => true, 'data' => $p]);
});

Route::post('/products', function (Request $request) {
    // Permission check
    $auth = $request->header('Authorization', '');
    if (!preg_match('/Bearer\s(\S+)/i', $auth, $m)) {
        return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHENTICATED']], 401);
    }
    $parts = explode('.', $m[1]);
    $payload = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);
    if (!in_array('catalog.create', $payload['permissions'] ?? [], true)) {
        return response()->json(['success' => false, 'error' => ['code' => 'PERMISSION_DENIED']], 403);
    }

    $sku = trim($request->input('sku', ''));
    $name = trim($request->input('name', ''));
    $grossPrice = (float) $request->input('gross_price', 0);

    if (empty($sku) || empty($name) || $grossPrice <= 0) {
        return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED']], 422);
    }

    $id = DB::transaction(function () use ($sku, $name, $grossPrice, $request) {
        $productId = DB::table('products')->insertGetId([
            'sku' => $sku,
            'barcode' => $request->input('barcode'),
            'name' => $name,
            'category_id' => $request->input('category_id', 1),
            'unit_id' => $request->input('unit_id', 1),
            'cost_price' => (float) $request->input('cost_price', 0),
            'gross_price' => $grossPrice,
            'min_stock' => (int) $request->input('min_stock', 0),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventId = (string) Str::uuid();
        DB::table('outbox_events')->insert([
            'event_id' => $eventId,
            'event_type' => 'ProductCreated',
            'aggregate_type' => 'Product',
            'aggregate_id' => (string) $productId,
            'routing_key' => 'catalog.product.created',
            'payload' => json_encode([
                'event_id' => $eventId,
                'event_type' => 'ProductCreated',
                'aggregate_type' => 'Product',
                'aggregate_id' => (string) $productId,
                'timestamp' => now()->toIso8601String(),
                'version' => '1.0',
                'producer' => 'catalog-service',
                'payload' => [
                    'product_id' => $productId,
                    'sku' => $sku,
                    'name' => $name,
                    'gross_price' => $grossPrice,
                ],
            ]),
            'status' => 'PENDING',
            'created_at' => now(),
        ]);

        return $productId;
    });

    return response()->json(['success' => true, 'data' => ['id' => $id, 'sku' => $sku]], 201);
});

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