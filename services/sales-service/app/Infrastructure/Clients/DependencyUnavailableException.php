<?php

namespace App\Infrastructure\Clients;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/** Service sở hữu dữ liệu không phản hồi / lỗi: trả 503 thay vì âm thầm dùng dữ liệu rỗng. */
class DependencyUnavailableException extends RuntimeException
{
    public function __construct(public readonly string $service, ?int $status = null, ?Throwable $previous = null)
    {
        parent::__construct("{$service} không phản hồi".($status ? " (HTTP {$status})" : ''), 0, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'DEPENDENCY_UNAVAILABLE',
                'message' => "Không lấy được dữ liệu từ {$this->service}, vui lòng thử lại sau.",
            ],
        ], 503);
    }
}
