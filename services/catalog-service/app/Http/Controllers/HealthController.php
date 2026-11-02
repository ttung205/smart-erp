<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

class HealthController extends Controller
{
    #[OA\Get(
        path: '/health',
        summary: 'Kiểm tra trạng thái dịch vụ',
        tags: ['Hệ thống'],
        security: [],
        responses: [new OA\Response(response: 200, description: 'Dịch vụ đang hoạt động')]
    )]
    public function check()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'service' => 'catalog-service',
                'framework' => 'Laravel 12 / 13',
                'status' => 'UP',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
