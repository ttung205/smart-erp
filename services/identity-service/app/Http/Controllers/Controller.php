<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Smart ERP - Identity Service API',
    description: 'Dịch vụ định danh: đăng nhập, cấp JWT, quản lý người dùng, vai trò, quyền và thông tin công ty. '
        .'Endpoint đọc dữ liệu chấp nhận JWT của người dùng hoặc khóa nội bộ của service khác.'
)]
#[OA\Server(url: 'http://localhost:8000/api/v1/auth', description: 'Qua gateway')]
#[OA\Server(url: 'http://127.0.0.1:8001/api', description: 'Trực tiếp (dev)')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT do endpoint /login cấp. Nhập token, không cần thêm chữ Bearer.'
)]
#[OA\SecurityScheme(
    securityScheme: 'internalServiceKey',
    type: 'apiKey',
    in: 'header',
    name: 'X-Internal-Service-Key',
    description: 'Khóa dùng chung cho service nội bộ (ví dụ notification-service, eoffice-service).'
)]
abstract class Controller
{
    //
}
