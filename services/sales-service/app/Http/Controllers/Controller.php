<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Smart ERP - Sales Service API',
    description: 'Chuỗi bán hàng: báo giá, đơn bán, yêu cầu giao hàng, chứng từ bán (giữ hàng nguyên tử qua inventory-service), '
        .'xuất kho, hóa đơn GTGT và điều chỉnh, trả hàng, báo cáo bán hàng và doanh thu. Mọi endpoint nghiệp vụ yêu cầu JWT và quyền tương ứng.'
)]
#[OA\Server(url: 'http://localhost:8000/api/v1/sales', description: 'Qua gateway')]
#[OA\Server(url: 'http://127.0.0.1:8005/api', description: 'Trực tiếp (dev)')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT do identity-service cấp qua /api/v1/auth/login. Nhập token, không cần thêm chữ Bearer.'
)]
#[OA\SecurityScheme(
    securityScheme: 'internalServiceKey',
    type: 'apiKey',
    in: 'header',
    name: 'X-Internal-Service-Key',
    description: 'Khóa dùng chung cho service nội bộ (ví dụ notification-service tải PDF báo giá).'
)]
abstract class Controller
{
    /** Mô tả lỗi dùng chung cho tài liệu OpenAPI của các controller con. */
    protected const DOC_401 = 'UNAUTHENTICATED: thiếu hoặc sai token';

    protected const DOC_404 = 'NOT_FOUND: không tìm thấy bản ghi';

    protected const DOC_422 = 'Dữ liệu không hợp lệ';

    protected const DOC_409_STATUS = 'INVALID_STATUS_TRANSITION: trạng thái hiện tại không cho phép thao tác này';
}
