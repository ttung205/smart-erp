<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\InvoiceController;

// 1. Health Check
Route::get('/health', [HealthController::class, 'check']);

// 2. Quotations (Báo Giá)
Route::post('/quotations/{id}/submit-approval', [QuotationController::class, 'submitApproval']);
Route::post('/quotations/{id}/approve', [QuotationController::class, 'approve']);
Route::post('/quotations/{id}/create-so', [QuotationController::class, 'createSO']);
Route::apiResource('quotations', QuotationController::class);

// 3. Sales Orders (Đơn Bán Hàng)
Route::apiResource('orders', SalesOrderController::class);

// 4. Delivery Requests (Yêu Cầu Giao Hàng)
Route::put('/deliveries/{id}/status', [DeliveryController::class, 'updateStatus']);
Route::apiResource('deliveries', DeliveryController::class);

// 5. Invoices (Chứng Từ & Kế Toán Kho)
Route::put('/invoices/{id}/warehouse-status', [InvoiceController::class, 'updateWarehouseStatus']);
Route::post('/invoices/{id}/post-accounting', [InvoiceController::class, 'postAccounting']);
Route::apiResource('invoices', InvoiceController::class);