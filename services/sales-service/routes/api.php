<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SaleReportController;
use App\Http\Controllers\VatInvoiceController;
use App\Http\Controllers\RevenueReportController;
use App\Http\Controllers\AttachmentController;

// 1. Health Check
Route::get('/health', [HealthController::class, 'check']);

// Attachments (Tệp đính kèm)
Route::get('/attachments', [AttachmentController::class, 'index']);
Route::post('/attachments/upload', [AttachmentController::class, 'upload']);
Route::get('/attachments/{id}/download', [AttachmentController::class, 'download']);
Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy']);

// 2. Quotations (Báo Giá)
Route::post('/quotations/{id}/submit-approval', [QuotationController::class, 'submitApproval']);
Route::post('/quotations/{id}/approve', [QuotationController::class, 'approve']);
Route::post('/quotations/{id}/reject', [QuotationController::class, 'reject']);
Route::post('/quotations/{id}/create-so', [QuotationController::class, 'createSO']);
Route::apiResource('quotations', QuotationController::class);

// 3. Sales Orders (Đơn Bán Hàng)
Route::post('/orders/{id}/submit-approval', [SalesOrderController::class, 'submitApproval']);
Route::post('/orders/{id}/approve', [SalesOrderController::class, 'approve']);
Route::post('/orders/{id}/reject', [SalesOrderController::class, 'reject']);
Route::apiResource('orders', SalesOrderController::class);

// 4. Delivery Requests (Yêu Cầu Giao Hàng)
Route::put('/deliveries/{id}/status', [DeliveryController::class, 'updateStatus']);
Route::apiResource('deliveries', DeliveryController::class);

// 5. Invoices (Chứng Từ & Kế Toán Kho)
Route::post('/invoices/{id}/send-to-warehouse', [InvoiceController::class, 'sendToWarehouse']);
Route::post('/invoices/{id}/sync-reserve', [InvoiceController::class, 'syncReserve']);
Route::put('/invoices/{id}/assign-logistics', [InvoiceController::class, 'assignLogistics']);
Route::post('/invoices/{id}/warehouse-post', [InvoiceController::class, 'warehousePost']);
Route::put('/invoices/{id}/warehouse-status', [InvoiceController::class, 'updateWarehouseStatus']);
Route::post('/invoices/{id}/post-accounting', [InvoiceController::class, 'postAccounting']);
Route::apiResource('invoices', InvoiceController::class);

// 6. VAT Invoices (Hóa Đơn GTGT)
Route::get('/vat-invoices/next-number', [VatInvoiceController::class, 'getNextInvoiceNumber']);
Route::get('/vat-invoices/unbilled-items', [VatInvoiceController::class, 'getUnbilledItems']);
Route::apiResource('vat-invoices', VatInvoiceController::class);

// 7. Reports: Sale Items (Báo Cáo Đơn Bán Hàng)
Route::get('/reports/sale-items', [SaleReportController::class, 'index']);
Route::get('/reports/sale-items/{id}', [SaleReportController::class, 'show']);
Route::get('/sale-report-items', [SaleReportController::class, 'index']);
Route::get('/sale-report-items/{id}', [SaleReportController::class, 'show']);

// 8. Reports: Revenue Items (Báo Cáo Doanh Thu TK 1311 & TK 1312)
Route::get('/reports/revenue-items', [RevenueReportController::class, 'index']);
Route::get('/reports/revenue-items/{id}', [RevenueReportController::class, 'show']);
Route::get('/revenue-report-items', [RevenueReportController::class, 'index']);
Route::get('/revenue-report-items/{id}', [RevenueReportController::class, 'show']);