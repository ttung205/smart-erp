<?php

use App\Services\LeaveRequestService;

return [

    // Số ngày phép năm mặc định cấp cho mỗi nhân viên (không cộng dồn sang năm sau)
    'annual_leave_days' => (float) env('EOFFICE_ANNUAL_LEAVE_DAYS', 12),

    // Phòng ban cấp cao nhất: trưởng phòng tự xin nghỉ sẽ được chuyển lên trưởng phòng này duyệt
    'top_department_code' => env('EOFFICE_TOP_DEPARTMENT_CODE', 'BOD'),

    // Nghiệp vụ xử lý khi người duyệt ra quyết định, theo morph alias của chứng từ
    'approval_handlers' => [
        'leave_request' => LeaveRequestService::class,
    ],

];
