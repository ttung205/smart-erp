<?php

namespace App\Infrastructure\Clients;

use App\Http\Middleware\CorrelationContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Http client gọi service nội bộ: chuyển tiếp JWT (không có người dùng, vd job đối soát, thì dùng khóa nội bộ),
 * X-Correlation-ID của request đang xử lý, timeout, circuit breaker theo service đích.
 * Retry chỉ dùng cho lời gọi an toàn khi gọi lại (đọc, hoặc lệnh kho idempotent theo số chứng từ).
 */
final class InternalHttp
{
    /**
     * Inventory. Retry mặc định bật cho lệnh đọc và lệnh kho idempotent theo số chứng từ (giữ hàng, nhả hàng,
     * xuất kho); tắt cho lệnh trả hàng vì Inventory không chống ghi trùng.
     */
    public static function inventory(?string $token, bool $retry = true): PendingRequest
    {
        return self::client('inventory-service', $token, (int) config('services.inventory.timeout', 3), $retry);
    }

    /** Finance: ghi công nợ / điều chỉnh hóa đơn không retry để tránh ghi trùng. */
    public static function finance(?string $token): PendingRequest
    {
        return self::client('finance-service', $token, (int) config('services.finance.timeout', 5), retry: false);
    }

    public static function client(string $service, ?string $token, int $timeout, bool $retry): PendingRequest
    {
        $request = ($token ? Http::withToken($token) : Http::withHeaders(['X-Internal-Service-Key' => (string) config('services.internal.key')]))
            ->acceptJson()
            ->withHeaders([CorrelationContext::HEADER => self::correlationId()])
            ->timeout($timeout)
            ->withMiddleware(CircuitBreaker::for($service)->middleware());

        return $retry ? $request->retry(2, 200, fn (Throwable $e): bool => self::isRetryable($e), throw: false) : $request;
    }

    /** Id của request đang xử lý; chạy từ command/job thì request() rỗng nên sinh id mới. */
    public static function correlationId(): string
    {
        return request()->header(CorrelationContext::HEADER) ?: (string) Str::uuid();
    }

    /** Chỉ retry lỗi kết nối thật; mạch đang mở thì trả lỗi ngay. */
    public static function isRetryable(Throwable $e): bool
    {
        return $e instanceof ConnectionException && ! $e instanceof CircuitOpenException;
    }
}
