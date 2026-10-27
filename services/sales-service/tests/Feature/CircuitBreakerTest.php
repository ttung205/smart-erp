<?php

namespace Tests\Feature;

use App\Infrastructure\Clients\CircuitBreaker;
use App\Infrastructure\Clients\CircuitOpenException;
use App\Infrastructure\Clients\InternalHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private const URL = 'http://inventory.test/api/v1/inventory/warehouses';

    private function callInventory(): int
    {
        return InternalHttp::inventory('token', retry: false)->get(self::URL)->status();
    }

    public function test_opens_after_consecutive_server_errors_and_rejects_without_calling(): void
    {
        Http::fake([self::URL => Http::response(['success' => false], 503)]);

        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->assertSame(503, $this->callInventory());
        }
        $this->assertSame(CircuitBreaker::OPEN, CircuitBreaker::for('inventory-service')->state());

        $this->expectException(CircuitOpenException::class);
        try {
            $this->callInventory();
        } finally {
            Http::assertSentCount(CircuitBreaker::FAILURE_THRESHOLD);
        }
    }

    public function test_client_errors_do_not_count_as_failures(): void
    {
        Http::fake([self::URL => Http::response(['error' => ['code' => 'INSUFFICIENT_STOCK']], 409)]);

        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD + 2; $i++) {
            $this->assertSame(409, $this->callInventory());
        }

        $this->assertSame(CircuitBreaker::CLOSED, CircuitBreaker::for('inventory-service')->state());
    }

    public function test_connection_errors_open_the_circuit(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            try {
                $this->callInventory();
                $this->fail('Expected a connection error.');
            } catch (ConnectionException $e) {
                $this->assertNotInstanceOf(CircuitOpenException::class, $e);
            }
        }

        $this->assertSame(CircuitBreaker::OPEN, CircuitBreaker::for('inventory-service')->state());
    }

    public function test_half_open_probe_closes_the_circuit_when_service_recovers(): void
    {
        Http::fakeSequence(self::URL)
            ->pushStatus(500)->pushStatus(500)->pushStatus(500)->pushStatus(500)->pushStatus(500)
            ->push(['success' => true], 200);

        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->callInventory();
        }
        $this->travel(CircuitBreaker::OPEN_SECONDS + 1)->seconds();
        $this->assertSame(CircuitBreaker::HALF_OPEN, CircuitBreaker::for('inventory-service')->state());

        $this->assertSame(200, $this->callInventory());
        $this->assertSame(CircuitBreaker::CLOSED, CircuitBreaker::for('inventory-service')->state());
    }

    public function test_half_open_probe_failure_reopens_the_circuit(): void
    {
        Http::fake([self::URL => Http::response([], 500)]);

        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->callInventory();
        }
        $this->travel(CircuitBreaker::OPEN_SECONDS + 1)->seconds();

        $this->assertSame(500, $this->callInventory());
        $this->assertSame(CircuitBreaker::OPEN, CircuitBreaker::for('inventory-service')->state());
    }

    public function test_breakers_are_separate_per_service(): void
    {
        Http::fake([self::URL => Http::response([], 503), 'http://finance.test/*' => Http::response([], 200)]);

        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->callInventory();
        }

        $this->assertSame(200, InternalHttp::finance('token')->get('http://finance.test/api/health')->status());
        $this->assertSame(CircuitBreaker::CLOSED, CircuitBreaker::for('finance-service')->state());
    }
}
