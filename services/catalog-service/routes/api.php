<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\WarehouseController;

// 1. Health check
Route::get('/health', [HealthController::class, 'check']);

// 2. Products (CRUD & Import Excel)
Route::post('/products/import', [ProductController::class, 'import']);
Route::apiResource('products', ProductController::class);

// 3. Brands (Thương hiệu)
Route::apiResource('brands', BrandController::class);

// 4. Categories (Danh mục)
Route::apiResource('categories', CategoryController::class);

// 5. Customers & Contacts
Route::get('/customers', [CustomerController::class, 'index']);
Route::post('/customers', [CustomerController::class, 'store']);
Route::get('/customers/{id}', [CustomerController::class, 'show']);
Route::put('/customers/{id}', [CustomerController::class, 'update']);
Route::post('/customers/{id}/contacts', [CustomerController::class, 'addContact']);

// 6. Partners & Warehouses
Route::get('/partners', [PartnerController::class, 'index']);
Route::get('/warehouses', [WarehouseController::class, 'index']);