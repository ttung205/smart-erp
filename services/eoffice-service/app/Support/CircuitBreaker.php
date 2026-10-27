<?php

namespace App\Support;

use Closure;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Circuit breaker cho lời gọi REST sang service khác, gắn vào Http client bằng withMiddleware().
 *
 * - CLOSED: gọi bình thường, đếm lỗi liên tiếp (lỗi kết nối hoặc HTTP 5xx; 4xx như 409 hết hàng không tính).
 * - OPEN: sau FAILURE_THRESHOLD lỗi liên tiếp, từ chối ngay trong OPEN_SECONDS giây (ném CircuitOpenException,
 *   là một ConnectionException nên các khối catch hiện có vẫn xử lý như "service không phản hồi").
 * - HALF_OPEN: hết thời gian mở, cho đúng một request thử; thành công thì đóng mạch, lỗi thì mở lại.
 *
 * Trạng thái lưu trong Laravel Cache nên dùng chung giữa các request của cùng service.
 */
final class CircuitBreaker
{
    public const FAILURE_THRESHOLD = 5;

    public const OPEN_SECONDS = 30;

    public const CLOSED = 'CLOSED';

    public const OPEN = 'OPEN';

    public const HALF_OPEN = 'HALF_OPEN';

    private function __construct(private readonly string $name) {}

    public static function for(string $name): self
    {
        return new self($name);
    }

    /** Guzzle middleware: Http::withMiddleware(CircuitBreaker::for('inventory')->middleware()) */
    public function middleware(): Closure
    {
        return fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler) {
            $this->beforeCall();

            try {
                $promise = $handler($request, $options);
            } catch (Throwable $e) {
                $this->recordFailure();

                throw $e;
            }

            return $promise->then(
                function (ResponseInterface $response) {
                    $response->getStatusCode() >= 500 ? $this->recordFailure() : $this->recordSuccess();

                    return $response;
                },
                function (Throwable $reason) {
                    $this->recordFailure();

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    public function state(): string
    {
        $openedUntil = Cache::get($this->key('opened_until'));
        if ($openedUntil === null) {
            return self::CLOSED;
        }

        return now()->getTimestamp() < $openedUntil ? self::OPEN : self::HALF_OPEN;
    }

    private function beforeCall(): void
    {
        $state = $this->state();
        if ($state === self::OPEN) {
            throw new CircuitOpenException($this->name);
        }
        // Nửa mở: chỉ một request được đi thử, các request khác vẫn bị từ chối
        if ($state === self::HALF_OPEN && ! Cache::add($this->key('probe'), true, self::OPEN_SECONDS)) {
            throw new CircuitOpenException($this->name);
        }
    }

    private function recordSuccess(): void
    {
        if ($this->state() !== self::CLOSED) {
            Log::info("[CircuitBreaker] {$this->name}: đóng mạch, service đã phục hồi");
        }
        Cache::forget($this->key('failures'));
        Cache::forget($this->key('opened_until'));
        Cache::forget($this->key('probe'));
    }

    private function recordFailure(): void
    {
        $halfOpen = $this->state() === self::HALF_OPEN;
        Cache::add($this->key('failures'), 0, 3600);
        $failures = (int) Cache::increment($this->key('failures'));

        if ($halfOpen || $failures >= self::FAILURE_THRESHOLD) {
            Cache::put($this->key('opened_until'), now()->getTimestamp() + self::OPEN_SECONDS, 3600);
            Cache::forget($this->key('probe'));
            Log::warning("[CircuitBreaker] {$this->name}: mở mạch ".self::OPEN_SECONDS."s sau {$failures} lỗi liên tiếp");
        }
    }

    private function key(string $suffix): string
    {
        return "circuit:{$this->name}:{$suffix}";
    }
}
