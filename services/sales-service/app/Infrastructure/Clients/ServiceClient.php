<?php

namespace App\Infrastructure\Clients;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Gọi REST sang service khác theo architecture.md §6: timeout, retry khi lỗi kết nối,
 * chuyển tiếp JWT người dùng (hoặc khóa nội bộ khi chạy job/command) và X-Correlation-ID.
 */
abstract class ServiceClient
{
    public function __construct(
        private readonly string $service,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    /** Trả về trường "data" của API Envelope; null khi 404. */
    protected function getData(string $path, array $query = []): mixed
    {
        try {
            $response = $this->http()->get($this->baseUrl.$path, $query);
        } catch (ConnectionException $e) {
            throw new DependencyUnavailableException($this->service, previous: $e);
        }

        if ($response->status() === 404) {
            return null;
        }
        if ($response->failed()) {
            throw new DependencyUnavailableException($this->service, $response->status());
        }

        return $response->json('data');
    }

    private function http(): PendingRequest
    {
        // Chạy từ command/job thì request() rỗng, không có token → dùng khóa nội bộ
        $incoming = request();
        $request = Http::acceptJson()
            ->timeout($this->timeout)
            ->retry(2, 200, fn ($e) => $e instanceof ConnectionException, throw: false)
            ->withHeaders(['X-Correlation-ID' => $incoming->header('X-Correlation-ID') ?: (string) Str::uuid()]);

        $token = $incoming->bearerToken();
        if ($token) {
            return $request->withToken($token);
        }

        return $request->withHeaders(['X-Internal-Service-Key' => (string) config('services.internal.key')]);
    }
}
