<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Giữ X-Correlation-ID xuyên suốt một request: ghi vào mọi dòng log (để tìm trong Loki bằng id) và để các
 * client gọi service khác chuyển tiếp cùng id. Kong sinh id khi client không gửi; gọi thẳng vào service thì tự sinh.
 */
class CorrelationContext
{
    public const HEADER = 'X-Correlation-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $id = self::sanitize($request->header(self::HEADER));
        // Các nơi đọc request()->header() (InternalHttp, ServiceClient, Outbox) sẽ thấy đúng id này
        $request->headers->set(self::HEADER, $id);
        Log::withContext(['correlation_id' => $id]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    /** Id do client đặt bị coi là không đáng tin: chỉ nhận ký tự an toàn, ngắn, để không chèn dòng vào log. */
    private static function sanitize(?string $value): string
    {
        if ($value !== null && preg_match('/^[A-Za-z0-9._-]{1,100}$/', $value) === 1) {
            return $value;
        }

        return (string) Str::uuid();
    }
}
