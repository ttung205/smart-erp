<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Smart ERP - Finance Service API',
    description: 'Dịch vụ tài chính kế toán: hệ thống tài khoản, sổ cái, sổ quỹ, sổ ngân hàng, công nợ phải thu, chứng từ thu chi và bút toán lương. '
        .'Mọi endpoint nghiệp vụ yêu cầu JWT và quyền tương ứng.'
)]
#[OA\Server(url: 'http://localhost:8000/api/v1/finance', description: 'Qua gateway')]
#[OA\Server(url: 'http://127.0.0.1:8008/api', description: 'Trực tiếp (dev)')]
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
    //
}
