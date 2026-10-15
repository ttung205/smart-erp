<?php

use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CustomerReceivableController;
use App\Http\Controllers\FinanceVoucherController;
use App\Http\Controllers\TreasuryController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['success' => true, 'service' => 'finance-service']));

Route::middleware('jwt')->group(function (): void {
    Route::get('/accounts', [ChartOfAccountController::class, 'index'])->middleware('permission:finance.view');
    Route::get('/master-data', [TreasuryController::class, 'masterData'])->middleware('permission:finance.view');
    Route::get('/cash-book', [TreasuryController::class, 'cashBook'])->middleware('permission:finance.view');
    Route::get('/bank-book', [TreasuryController::class, 'bankBook'])->middleware('permission:finance.view');
    Route::get('/customer-receivables', [CustomerReceivableController::class, 'index'])->middleware('permission:finance.view');
    Route::post('/customer-receivables/sync', [CustomerReceivableController::class, 'sync'])->middleware('permission:finance.approve,invoice.manage');
    Route::get('/vouchers', [FinanceVoucherController::class, 'index'])->middleware('permission:finance.view');
    Route::post('/vouchers', [FinanceVoucherController::class, 'store'])->middleware('permission:finance.create,finance.approve');
    Route::post('/vouchers/{voucher}/post', [FinanceVoucherController::class, 'post'])->middleware('permission:finance.post,finance.approve');
    Route::post('/vouchers/{voucher}/allocations', [FinanceVoucherController::class, 'allocate'])->middleware('permission:finance.post,finance.approve');
});
