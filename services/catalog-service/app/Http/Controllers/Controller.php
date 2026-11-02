<?php

namespace App\Http\Controllers;

use App\Auth\Actor;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Smart ERP - Catalog Service API',
    description: 'Dịch vụ danh mục: sản phẩm, lịch sử giá, thương hiệu, danh mục, khách hàng, nhà cung cấp và đối tác. '
        .'Route đọc sản phẩm và khách hàng chấp nhận JWT hoặc khóa nội bộ để service khác (sales, purchase) lấy dữ liệu snapshot.'
)]
#[OA\Server(url: 'http://localhost:8000/api/v1/catalog', description: 'Qua gateway')]
#[OA\Server(url: 'http://127.0.0.1:8002/api', description: 'Trực tiếp (dev)')]
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
    description: 'Khóa dùng chung cho service nội bộ (sales, purchase).'
)]
abstract class Controller
{
    /** Người dùng đã được middleware jwt xác thực. */
    protected function actor(Request $request): Actor
    {
        return $request->attributes->get('actor');
    }
}
