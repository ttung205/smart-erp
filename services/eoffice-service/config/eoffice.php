<?php

use App\Services\AttendanceRequestService;
use App\Services\LeaveRequestService;
use App\Services\OfficeRequestService;

return [

    // Số ngày phép năm mặc định cấp cho mỗi nhân viên (không cộng dồn sang năm sau)
    'annual_leave_days' => (float) env('EOFFICE_ANNUAL_LEAVE_DAYS', 12),

    // Phòng ban cấp cao nhất: trưởng phòng tự xin nghỉ sẽ được chuyển lên trưởng phòng này duyệt
    'top_department_code' => env('EOFFICE_TOP_DEPARTMENT_CODE', 'BOD'),

    // Nghiệp vụ xử lý khi người duyệt ra quyết định, theo morph alias của chứng từ
    'approval_handlers' => [
        'leave_request' => LeaveRequestService::class,
        'office_request' => OfficeRequestService::class,
        'attendance_request' => AttendanceRequestService::class,
    ],

    // Cho phép người lập bảng lương tự duyệt (chỉ nên bật ở môi trường thử nghiệm một người dùng)
    'payroll_allow_self_approval' => (bool) env('EOFFICE_PAYROLL_ALLOW_SELF_APPROVAL', false),

    // Yêu cầu bổ sung / bù công / OT phải gửi trong vòng bao nhiêu ngày làm việc kể từ ngày phát sinh
    'attendance_request_deadline_days' => (int) env('EOFFICE_ATTENDANCE_REQUEST_DEADLINE_DAYS', 3),

];
