<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Smart ERP - E-Office Service API',
    description: 'Văn phòng số: nghỉ phép, đề nghị mua sắm và thanh toán, luồng duyệt chung, chấm công, yêu cầu công và tính lương. '
        .'Dữ liệu lương dùng quyền payroll.* riêng, quyền eoffice.manage không đi tắt được.'
)]
#[OA\Server(url: 'http://localhost:8000/api/v1/eoffice', description: 'Qua gateway')]
#[OA\Server(url: 'http://127.0.0.1:8007/api', description: 'Trực tiếp (dev)')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT do identity-service cấp qua /api/v1/auth/login. Nhập token, không cần thêm chữ Bearer.'
)]
#[OA\Get(
    path: '/health',
    summary: 'Kiểm tra trạng thái dịch vụ',
    tags: ['Hệ thống'],
    security: [],
    responses: [new OA\Response(response: 200, description: 'Dịch vụ đang hoạt động')]
)]
abstract class Controller
{
    /** Mô tả lỗi dùng chung cho tài liệu OpenAPI của các controller con. */
    protected const DOC_401 = 'UNAUTHENTICATED: thiếu hoặc sai token';

    protected const DOC_403 = 'FORBIDDEN: không đủ quyền hoặc không phải người được phép thao tác';

    protected const DOC_404 = 'Không tìm thấy bản ghi';

    protected const DOC_422 = 'Dữ liệu không hợp lệ hoặc vi phạm quy tắc nghiệp vụ (trạng thái không cho phép, trùng lặp...)';
}
