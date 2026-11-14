<?php

namespace Tests\Feature;

use App\Infrastructure\Clients\InternalHttp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CorrelationIdTest extends TestCase
{
    private const URL = 'http://inventory.test/api/v1/inventory/stocks/reserve/batch';

    public function test_keeps_the_incoming_id_and_returns_it_in_the_response(): void
    {
        $this->withHeader('X-Correlation-ID', 'req-abc-123')
            ->getJson('/api/health/live')
            ->assertOk()
            ->assertHeader('X-Correlation-ID', 'req-abc-123');
    }

    public function test_generates_an_id_when_the_client_sends_none(): void
    {
        $response = $this->getJson('/api/health/live')->assertOk();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            (string) $response->headers->get('X-Correlation-ID'),
        );
    }

    public function test_replaces_an_unsafe_id_so_it_cannot_inject_log_lines(): void
    {
        $response = $this->withHeader('X-Correlation-ID', 'x y"{}')
            ->getJson('/api/health/live')
            ->assertOk();

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $response->headers->get('X-Correlation-ID'));
    }

    public function test_adds_the_id_to_the_log_context(): void
    {
        Log::partialMock()->shouldReceive('withContext')->once()->with(['correlation_id' => 'req-log-1']);

        $this->withHeader('X-Correlation-ID', 'req-log-1')->getJson('/api/health/live')->assertOk();
    }

    public function test_forwards_the_id_when_calling_inventory(): void
    {
        Http::fake([self::URL => Http::response(['success' => true], 200)]);
        $this->app->instance('request', Request::create('/x', 'POST', server: ['HTTP_X_CORRELATION_ID' => 'req-fwd-1']));

        InternalHttp::inventory('token', retry: false)->post(self::URL, []);

        Http::assertSent(fn ($request) => $request->header('X-Correlation-ID') === ['req-fwd-1']);
    }

    public function test_sends_a_generated_id_from_a_command_with_no_request(): void
    {
        Http::fake([self::URL => Http::response(['success' => true], 200)]);

        InternalHttp::inventory(null, retry: false)->post(self::URL, []);

        Http::assertSent(fn ($request) => preg_match('/^[0-9a-f-]{36}$/', $request->header('X-Correlation-ID')[0] ?? '') === 1);
    }
}
