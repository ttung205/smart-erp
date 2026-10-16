<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RevenueReportController;
use App\Http\Controllers\SaleReportController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\SaleReturnRequestController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\VatInvoiceAdjustmentController;
use App\Http\Controllers\VatInvoiceController;
use Illuminate\Support\Facades\Route;

// 1. Health Check
Route::get('/health', [HealthController::class, 'check']);

Route::middleware('jwt')->group(function () {
    Route::get('/sale-return-requests/eligible-invoices', [SaleReturnRequestController::class, 'eligibleInvoices'])->middleware('permission:sale_return.view');
    Route::get('/sale-return-requests', [SaleReturnRequestController::class, 'index'])->middleware('permission:sale_return.view');
    Route::post('/sale-return-requests', [SaleReturnRequestController::class, 'store'])->middleware('permission:sale_return.create');
    Route::get('/sale-return-requests/{saleReturnRequest}', [SaleReturnRequestController::class, 'show'])->middleware('permission:sale_return.view');
    Route::post('/sale-return-requests/{saleReturnRequest}/start-inspection', [SaleReturnRequestController::class, 'startInspection'])->middleware('permission:sale_return.technical');
    Route::put('/sale-return-requests/{saleReturnRequest}/inspection', [SaleReturnRequestController::class, 'inspect'])->middleware('permission:sale_return.technical');
    Route::post('/sale-return-requests/{saleReturnRequest}/reject', [SaleReturnRequestController::class, 'reject'])->middleware('permission:sale_return.technical');
    Route::post('/sale-return-requests/{saleReturnRequest}/receive-technical', [SaleReturnRequestController::class, 'receiveTechnical'])->middleware('permission:sale_return.technical');
    Route::post('/sale-return-requests/{saleReturnRequest}/request-main-warehouse', [SaleReturnRequestController::class, 'requestMainWarehouse'])->middleware('permission:sale_return.technical');
    Route::post('/sale-return-requests/{saleReturnRequest}/approve-main', [SaleReturnRequestController::class, 'approveMain'])->middleware('permission:sale_return.approve');
    Route::post('/sale-return-requests/{saleReturnRequest}/reject-main', [SaleReturnRequestController::class, 'rejectMain'])->middleware('permission:sale_return.approve');
    Route::post('/sale-return-requests/{saleReturnRequest}/post', [SaleReturnController::class, 'post'])->middleware('permission:sale_return.post');
    Route::get('/sale-returns', [SaleReturnController::class, 'index'])->middleware('permission:sale_return.view');
    Route::get('/sale-returns/{saleReturn}', [SaleReturnController::class, 'show'])->middleware('permission:sale_return.view');

    // Attachments (Tệp đính kèm)
    Route::get('/attachments', [AttachmentController::class, 'index'])->middleware('permission:quotation.view,order.view,invoice.view,sale_return.view');
    Route::post('/attachments/upload', [AttachmentController::class, 'upload'])->middleware('permission:quotation.create,quotation.update,order.create,order.update,invoice.create,sale_return.create,sale_return.technical');
    Route::get('/attachments/{id}/download', [AttachmentController::class, 'download'])->middleware('permission:quotation.view,order.view,invoice.view,sale_return.view');
    Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy'])->middleware('permission:quotation.update,order.update,invoice.manage,sale_return.technical,sale_return.manage');

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
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:sale-invoice.create');

    // 6. VAT Invoices (Hóa Đơn GTGT)
    Route::get('/vat-invoices/next-number', [VatInvoiceController::class, 'getNextInvoiceNumber'])->middleware('permission:invoice.create');
    Route::post('/vat-invoices/sync-finance', [VatInvoiceController::class, 'syncFinance'])->middleware('permission:finance.approve');
    Route::get('/vat-invoices/unbilled-items', [VatInvoiceController::class, 'getUnbilledItems'])->middleware('permission:invoice.view');
    Route::get('/vat-invoices', [VatInvoiceController::class, 'index'])->middleware('permission:invoice.view');
    Route::get('/vat-invoices/{vat_invoice}', [VatInvoiceController::class, 'show'])->middleware('permission:invoice.view');
    Route::post('/vat-invoices', [VatInvoiceController::class, 'store'])->middleware('permission:finance.approve,invoice.manage');
    Route::get('/vat-invoice-adjustments', [VatInvoiceAdjustmentController::class, 'index'])->middleware('permission:invoice.view,finance.view');
    Route::get('/vat-invoice-adjustments/{adjustment}', [VatInvoiceAdjustmentController::class, 'show'])->middleware('permission:invoice.view,finance.view');
    Route::post('/vat-invoice-adjustments', [VatInvoiceAdjustmentController::class, 'store'])->middleware('permission:finance.create');
    Route::post('/vat-invoice-adjustments/{adjustment}/approve', [VatInvoiceAdjustmentController::class, 'approve'])->middleware('permission:finance.approve');
    Route::post('/vat-invoice-adjustments/{adjustment}/issue', [VatInvoiceAdjustmentController::class, 'issue'])->middleware('permission:finance.approve');
    Route::post('/sale-returns/{saleReturn}/vat-invoice-adjustments', [VatInvoiceAdjustmentController::class, 'fromSaleReturn'])->middleware('permission:finance.create');

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
