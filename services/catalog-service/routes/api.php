<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SupplierController;

// 1. Health check (công khai)
Route::get('/health', [HealthController::class, 'check']);

Route::middleware('jwt')->group(function () {
    // 2. Products (CRUD & Import Excel)
    Route::post('/products/import', [ProductController::class, 'import'])->middleware('permission:catalog.create');
    Route::post('/products/apply-inquiry-result', [ProductController::class, 'applyInquiryResult'])->middleware('internal');
    Route::get('/products/price-history', [ProductController::class, 'priceHistory']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:catalog.create');
    Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->middleware('permission:catalog.edit');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:catalog.manage');

    // 3. Brands (Thương hiệu)
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brands/{brand}', [BrandController::class, 'show']);
    Route::post('/brands', [BrandController::class, 'store'])->middleware('permission:catalog.create');
    Route::match(['put', 'patch'], '/brands/{brand}', [BrandController::class, 'update'])->middleware('permission:catalog.edit');
    Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->middleware('permission:catalog.manage');

    // 4. Categories (Danh mục)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:catalog.create');
    Route::match(['put', 'patch'], '/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:catalog.edit');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:catalog.manage');

    // 5. Customers & Contacts (chưa có quyền ghi riêng nên dùng quyền của màn hình khách hàng)
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::middleware('permission:customer.view')->group(function () {
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::put('/customers/{id}', [CustomerController::class, 'update']);
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);
        Route::post('/customers/{id}/contacts', [CustomerController::class, 'addContact']);
        Route::put('/customers/{customerId}/contacts/{contactId}', [CustomerController::class, 'updateContact']);
        Route::delete('/customers/{customerId}/contacts/{contactId}', [CustomerController::class, 'deleteContact']);
    });

    // 6. Suppliers (Nhà cung cấp), tương tự khách hàng
    Route::get('/suppliers/generate-code', [SupplierController::class, 'generateCode'])->middleware('permission:supplier.view');
    Route::apiResource('suppliers', SupplierController::class)->only(['index', 'show']);
    Route::apiResource('suppliers', SupplierController::class)->except(['index', 'show'])->middleware('permission:supplier.view');

    // 7. Partners
    Route::get('/partners', [PartnerController::class, 'index']);
});
