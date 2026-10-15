<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveSetupController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['success' => true, 'service' => 'eoffice-service']));

Route::middleware('jwt')->group(function (): void {
    // Đơn xin nghỉ phép (mọi nhân viên đăng nhập đều dùng được cho đơn của mình)
    Route::post('/leave-requests/preview', [LeaveRequestController::class, 'preview']);
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show']);
    Route::put('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update']);
    Route::delete('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy']);
    Route::post('/leave-requests/{leaveRequest}/submit', [LeaveRequestController::class, 'submit']);
    Route::post('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);

    Route::get('/leave-types', [LeaveSetupController::class, 'leaveTypes']);
    Route::get('/holidays', [LeaveSetupController::class, 'holidays']);
    Route::get('/leave-balances/me', [LeaveSetupController::class, 'myBalance']);
    Route::get('/leave-calendar', [LeaveSetupController::class, 'calendar']);

    // Duyệt đơn: chỉ người được gán ở bước duyệt mới thao tác được (kiểm tra trong ApprovalService)
    Route::get('/approvals/inbox', [ApprovalController::class, 'inbox']);
    Route::post('/approvals/{step}/approve', [ApprovalController::class, 'approve']);
    Route::post('/approvals/{step}/reject', [ApprovalController::class, 'reject']);
    Route::post('/approvals/{step}/return', [ApprovalController::class, 'return']);

    // Quản trị (HR / Admin)
    Route::middleware('permission:eoffice.manage')->group(function (): void {
        Route::post('/leave-types', [LeaveSetupController::class, 'saveLeaveType']);
        Route::put('/leave-types/{leaveType}', [LeaveSetupController::class, 'saveLeaveType']);
        Route::post('/holidays', [LeaveSetupController::class, 'storeHoliday']);
        Route::delete('/holidays/{holiday}', [LeaveSetupController::class, 'destroyHoliday']);
        Route::get('/leave-balances', [LeaveSetupController::class, 'balances']);
        Route::get('/leave-balances/pro-rata', [LeaveSetupController::class, 'proRata']);
        Route::put('/leave-balances/{userId}', [LeaveSetupController::class, 'updateBalance'])->whereNumber('userId');
        Route::get('/leave-balances/{userId}/requests', [LeaveSetupController::class, 'userRequests'])->whereNumber('userId');
    });
});
