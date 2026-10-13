<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RevenueReportController;
use App\Http\Controllers\SaleReportController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\VatInvoiceController;
use Illuminate\Support\Facades\Route;

// 1. Health Check
Route::get('/health', [HealthController::class, 'check']);

Route::middleware('jwt')->group(function () {
    // Attachments (Tệp đính kèm)
    Route::get('/attachments', [AttachmentController::class, 'index'])->middleware('permission:quotation.view,order.view,invoice.view');
    Route::post('/attachments/upload', [AttachmentController::class, 'upload'])->middleware('permission:quotation.create,quotation.update,order.create,order.update,invoice.create');
    Route::get('/attachments/{id}/download', [AttachmentController::class, 'download'])->middleware('permission:quotation.view,order.view,invoice.view');
    Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy'])->middleware('permission:quotation.update,order.update,invoice.manage');

    // 2. Quotations (Báo Giá)
    Route::post('/quotations/{id}/submit-approval', [QuotationController::class, 'submitApproval'])->middleware('permission:quotation.update');
    Route::post('/quotations/{id}/approve', [QuotationController::class, 'approve'])->middleware('permission:quotation.approve');
    Route::post('/quotations/{id}/reject', [QuotationController::class, 'reject'])->middleware('permission:quotation.approve');
    Route::post('/quotations/{id}/create-so', [QuotationController::class, 'createSO'])->middleware('permission:order.create');
    Route::get('/quotations', [QuotationController::class, 'index'])->middleware('permission:quotation.view');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->middleware('permission:quotation.view');
    Route::post('/quotations', [QuotationController::class, 'store'])->middleware('permission:quotation.create');
    Route::match(['put', 'patch'], '/quotations/{quotation}', [QuotationController::class, 'update'])->middleware('permission:quotation.update');
    Route::delete('/quotations/{quotation}', [QuotationController::class, 'destroy'])->middleware('permission:quotation.cancel');

    // 3. Sales Orders (Đơn Bán Hàng)
    Route::post('/orders/{id}/submit-approval', [SalesOrderController::class, 'submitApproval'])->middleware('permission:order.update');
    Route::post('/orders/{id}/approve', [SalesOrderController::class, 'approve'])->middleware('permission:order.approve');
    Route::post('/orders/{id}/reject', [SalesOrderController::class, 'reject'])->middleware('permission:order.approve');
    Route::get('/orders', [SalesOrderController::class, 'index'])->middleware('permission:order.view');
    Route::get('/orders/{order}', [SalesOrderController::class, 'show'])->middleware('permission:order.view');
    Route::post('/orders', [SalesOrderController::class, 'store'])->middleware('permission:order.create');
    Route::match(['put', 'patch'], '/orders/{order}', [SalesOrderController::class, 'update'])->middleware('permission:order.update');
    Route::delete('/orders/{order}', [SalesOrderController::class, 'destroy'])->middleware('permission:order.cancel');

    // 4. Delivery Requests (Yêu Cầu Giao Hàng)
    Route::put('/deliveries/{id}/status', [DeliveryController::class, 'updateStatus'])->middleware('permission:delivery.manage');
    Route::get('/deliveries', [DeliveryController::class, 'index'])->middleware('permission:delivery.view');
    Route::post('/deliveries', [DeliveryController::class, 'store'])->middleware('permission:delivery.create');

    // 5. Invoices (Chứng Từ & Kế Toán Kho)
    Route::post('/invoices/{id}/send-to-warehouse', [InvoiceController::class, 'sendToWarehouse'])->middleware('permission:invoice.manage');
    Route::post('/invoices/{id}/sync-reserve', [InvoiceController::class, 'syncReserve'])->middleware('permission:invoice.manage');
    Route::put('/invoices/{id}/assign-logistics', [InvoiceController::class, 'assignLogistics'])->middleware('permission:invoice.manage');
    Route::post('/invoices/{id}/warehouse-post', [InvoiceController::class, 'warehousePost'])->middleware('permission:inventory.issue,invoice.manage');
    Route::put('/invoices/{id}/warehouse-status', [InvoiceController::class, 'updateWarehouseStatus'])->middleware('permission:invoice.manage');
    Route::post('/invoices/{id}/post-accounting', [InvoiceController::class, 'postAccounting'])->middleware('permission:finance.approve');
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:invoice.view');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoice.view');
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:invoice.create');

    // 6. VAT Invoices (Hóa Đơn GTGT)
    Route::get('/vat-invoices/next-number', [VatInvoiceController::class, 'getNextInvoiceNumber'])->middleware('permission:invoice.create');
    Route::get('/vat-invoices/unbilled-items', [VatInvoiceController::class, 'getUnbilledItems'])->middleware('permission:invoice.view');
    Route::get('/vat-invoices', [VatInvoiceController::class, 'index'])->middleware('permission:invoice.view');
    Route::get('/vat-invoices/{vat_invoice}', [VatInvoiceController::class, 'show'])->middleware('permission:invoice.view');
    Route::post('/vat-invoices', [VatInvoiceController::class, 'store'])->middleware('permission:finance.approve,invoice.manage');

    // 7. Reports: Sale Items (Báo Cáo Đơn Bán Hàng)
    Route::get('/reports/sale-items', [SaleReportController::class, 'index'])->middleware('permission:order.view');
    Route::get('/reports/sale-items/{id}', [SaleReportController::class, 'show'])->middleware('permission:order.view');
    Route::get('/sale-report-items', [SaleReportController::class, 'index'])->middleware('permission:order.view');
    Route::get('/sale-report-items/{id}', [SaleReportController::class, 'show'])->middleware('permission:order.view');

    // 8. Reports: Revenue Items (Báo Cáo Doanh Thu TK 1311 & TK 1312)
    Route::get('/reports/revenue-items', [RevenueReportController::class, 'index'])->middleware('permission:finance.view,invoice.view');
    Route::get('/reports/revenue-items/{id}', [RevenueReportController::class, 'show'])->middleware('permission:finance.view,invoice.view');
    Route::get('/revenue-report-items', [RevenueReportController::class, 'index'])->middleware('permission:finance.view,invoice.view');
    Route::get('/revenue-report-items/{id}', [RevenueReportController::class, 'show'])->middleware('permission:finance.view,invoice.view');
});
