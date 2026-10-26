<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttendanceAdjustmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceRequestController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveSetupController;
use App\Http\Controllers\OfficeRequestController;
use App\Http\Controllers\PayrollController;
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

    // Hai loại đề nghị có URL riêng; cùng dùng luồng duyệt E-Office.
    Route::get('/{kind}-requests', [OfficeRequestController::class, 'index'])->whereIn('kind', ['purchase', 'payment']);
    Route::post('/{kind}-requests', [OfficeRequestController::class, 'store'])->whereIn('kind', ['purchase', 'payment']);
    Route::get('/{kind}-requests/{officeRequest}', [OfficeRequestController::class, 'show'])->whereIn('kind', ['purchase', 'payment']);
    Route::put('/{kind}-requests/{officeRequest}', [OfficeRequestController::class, 'update'])->whereIn('kind', ['purchase', 'payment']);
    Route::delete('/{kind}-requests/{officeRequest}', [OfficeRequestController::class, 'destroy'])->whereIn('kind', ['purchase', 'payment']);
    Route::post('/{kind}-requests/{officeRequest}/submit', [OfficeRequestController::class, 'submit'])->whereIn('kind', ['purchase', 'payment']);
    Route::post('/{kind}-requests/{officeRequest}/cancel', [OfficeRequestController::class, 'cancel'])->whereIn('kind', ['purchase', 'payment']);
    Route::post('/{kind}-requests/{officeRequest}/attachments', [OfficeRequestController::class, 'upload'])->whereIn('kind', ['purchase', 'payment']);
    Route::get('/{kind}-requests/{officeRequest}/attachments/{attachment}', [OfficeRequestController::class, 'download'])->whereIn('kind', ['purchase', 'payment']);
    Route::delete('/{kind}-requests/{officeRequest}/attachments/{attachment}', [OfficeRequestController::class, 'removeAttachment'])->whereIn('kind', ['purchase', 'payment']);

    Route::get('/leave-types', [LeaveSetupController::class, 'leaveTypes']);
    Route::get('/holidays', [LeaveSetupController::class, 'holidays']);
    Route::get('/leave-balances/me', [LeaveSetupController::class, 'myBalance']);
    Route::get('/leave-calendar', [LeaveSetupController::class, 'calendar']);

    // Duyệt đơn: chỉ người được gán ở bước duyệt mới thao tác được (kiểm tra trong ApprovalService)
    Route::get('/approvals/inbox', [ApprovalController::class, 'inbox']);
    Route::post('/approvals/{step}/approve', [ApprovalController::class, 'approve']);
    Route::post('/approvals/{step}/reject', [ApprovalController::class, 'reject']);
    Route::post('/approvals/{step}/return', [ApprovalController::class, 'return']);

    // Chấm công: ai cũng xem/chấm công của mình; xem người khác theo quyền hoặc quan hệ quản lý (kiểm tra trong controller)
    Route::get('/attendance/me', [AttendanceController::class, 'me']);
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::get('/attendance/days', [AttendanceController::class, 'days']);
    Route::get('/shifts', [EmployeeProfileController::class, 'shifts']);
    Route::get('/attendance/punches', [AttendanceAdjustmentController::class, 'punches']);

    // Yêu cầu bổ sung công / bù công / remote / công tác / OT (đi qua luồng duyệt chung)
    Route::get('/attendance-requests', [AttendanceRequestController::class, 'index']);
    Route::post('/attendance-requests', [AttendanceRequestController::class, 'store']);
    Route::get('/attendance-requests/{attendanceRequest}', [AttendanceRequestController::class, 'show']);
    Route::put('/attendance-requests/{attendanceRequest}', [AttendanceRequestController::class, 'update']);
    Route::delete('/attendance-requests/{attendanceRequest}', [AttendanceRequestController::class, 'destroy']);
    Route::post('/attendance-requests/{attendanceRequest}/submit', [AttendanceRequestController::class, 'submit']);
    Route::post('/attendance-requests/{attendanceRequest}/cancel', [AttendanceRequestController::class, 'cancel']);

    Route::middleware('permission:attendance.view,attendance.manage')->group(function (): void {
        Route::get('/attendance/summary', [AttendanceController::class, 'summary']);
        Route::get('/employee-profiles', [EmployeeProfileController::class, 'index']);
        // attendance.view = quyền xem toàn bộ dữ liệu chấm công (chỉ đọc): bảng công, log, yêu cầu, nhật ký điều chỉnh, lịch sử nhập file
        Route::get('/attendance/import-batches', [AttendanceController::class, 'batches']);
        Route::get('/attendance/audit-logs', [AttendanceAdjustmentController::class, 'auditLogs']);
    });

    Route::middleware('permission:attendance.manage')->group(function (): void {
        Route::post('/attendance/import', [AttendanceController::class, 'import']);
        Route::post('/attendance/recalculate', [AttendanceController::class, 'recalculate']);
        Route::post('/attendance/manual-work', [AttendanceAdjustmentController::class, 'addWork']);
        Route::post('/attendance/clear-day', [AttendanceAdjustmentController::class, 'clearDay']);
        Route::post('/attendance/punches/{log}/void', [AttendanceAdjustmentController::class, 'voidPunch'])->whereNumber('log');
        Route::post('/employee-profiles/sync', [EmployeeProfileController::class, 'sync']);
        Route::put('/employee-profiles/{profile}', [EmployeeProfileController::class, 'update'])->whereNumber('profile');
        Route::post('/shifts', [EmployeeProfileController::class, 'saveShift']);
        Route::put('/shifts/{shift}', [EmployeeProfileController::class, 'saveShift'])->whereNumber('shift');
    });

    // Phiếu lương: nhân viên chỉ xem phiếu của mình khi kỳ lương đã được duyệt
    Route::get('/payroll/my-slips', [PayrollController::class, 'mySlips']);
    Route::get('/payroll/slips/{slip}', [PayrollController::class, 'showSlip'])->whereNumber('slip');

    Route::middleware('strict:payroll.view,payroll.manage,payroll.approve,payroll.pay')->group(function (): void {
        Route::get('/payroll/rules', [PayrollController::class, 'rules']);
        Route::get('/payroll/periods', [PayrollController::class, 'periods']);
        Route::get('/payroll/periods/{period}', [PayrollController::class, 'showPeriod'])->whereNumber('period');
        Route::get('/payroll/periods/{period}/adjustments', [PayrollController::class, 'adjustments'])->whereNumber('period');
    });

    Route::middleware('strict:payroll.manage')->group(function (): void {
        Route::put('/payroll/rules/{key}', [PayrollController::class, 'updateRule']);
        Route::post('/payroll/periods', [PayrollController::class, 'storePeriod']);
        Route::post('/payroll/periods/{period}/lock-attendance', [PayrollController::class, 'lockAttendance'])->whereNumber('period');
        Route::post('/payroll/periods/{period}/unlock-attendance', [PayrollController::class, 'unlockAttendance'])->whereNumber('period');
        Route::post('/payroll/periods/{period}/calculate', [PayrollController::class, 'calculate'])->whereNumber('period');
        Route::post('/payroll/periods/{period}/submit', [PayrollController::class, 'submit'])->whereNumber('period');
        Route::post('/payroll/periods/{period}/adjustments', [PayrollController::class, 'storeAdjustment'])->whereNumber('period');
        Route::delete('/payroll/adjustments/{adjustment}', [PayrollController::class, 'destroyAdjustment'])->whereNumber('adjustment');
    });

    Route::middleware('strict:payroll.approve')->group(function (): void {
        Route::post('/payroll/periods/{period}/approve', [PayrollController::class, 'approve'])->whereNumber('period');
        Route::post('/payroll/periods/{period}/return', [PayrollController::class, 'returnPeriod'])->whereNumber('period');
    });

    Route::post('/payroll/periods/{period}/pay', [PayrollController::class, 'pay'])->whereNumber('period')->middleware('strict:payroll.pay');

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
