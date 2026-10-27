<?php

namespace Tests\Feature;

use App\Console\Commands\RelayOutboxEvents;
use App\Messaging\RabbitMqPublisher;
use App\Messaging\UnroutableMessageException;
use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OutboxRelayResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function recordEvent(string $routingKey = 'sales.order.submitted'): OutboxEvent
    {
        return OutboxEvent::create([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'SalesOrderSubmitted',
            'aggregate_type' => 'SalesOrder',
            'aggregate_id' => '1',
            'routing_key' => $routingKey,
            'payload' => '{}',
            'status' => OutboxEvent::PENDING,
            'retry_count' => 0,
        ]);
    }

    public function test_event_is_never_given_up_while_the_broker_is_down_for_a_long_time(): void
    {
        $event = $this->recordEvent();

        $down = $this->mock(RabbitMqPublisher::class);
        $down->shouldReceive('publish')->andThrow(new \RuntimeException('broker down'));
        $down->shouldReceive('close');
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();
            $this->travel(61)->seconds();
        }
        $this->assertSame(OutboxEvent::FAILED, $event->fresh()->status);
        $this->assertSame(30, $event->fresh()->retry_count);

        $up = $this->mock(RabbitMqPublisher::class);
        $up->shouldReceive('publish')->once()->with('sales.order.submitted', '{}', $event->event_id);
        $up->shouldReceive('close');
        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();

        $this->assertSame(OutboxEvent::PUBLISHED, $event->fresh()->status);
    }

    public function test_broker_failure_stops_the_batch_to_keep_publish_order(): void
    {
        $first = $this->recordEvent();
        $second = $this->recordEvent();

        $down = $this->mock(RabbitMqPublisher::class);
        $down->shouldReceive('publish')->once()->andThrow(new \RuntimeException('broker down'));
        $down->shouldReceive('close');
        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();

        $this->assertSame(OutboxEvent::FAILED, $first->fresh()->status);
        $this->assertSame(OutboxEvent::PENDING, $second->fresh()->status);
    }

    public function test_unroutable_event_does_not_block_later_events(): void
    {
        $unroutable = $this->recordEvent('sales.unknown.thing');
        $next = $this->recordEvent();

        $publisher = $this->mock(RabbitMqPublisher::class);
        $publisher->shouldReceive('publish')->with('sales.unknown.thing', '{}', $unroutable->event_id)->andThrow(new UnroutableMessageException);
        $publisher->shouldReceive('publish')->with('sales.order.submitted', '{}', $next->event_id)->once();
        $publisher->shouldReceive('close');
        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();

        $this->assertSame(OutboxEvent::FAILED, $unroutable->fresh()->status);
        $this->assertSame(OutboxEvent::PUBLISHED, $next->fresh()->status);
    }

    public function test_failed_event_waits_before_the_next_attempt(): void
    {
        $event = $this->recordEvent();
        $event->update(['status' => OutboxEvent::FAILED, 'retry_count' => 3]);

        $this->assertFalse(RelayOutboxEvents::isDue($event->fresh(), now()->addSeconds(7)));
        $this->assertTrue(RelayOutboxEvents::isDue($event->fresh(), now()->addSeconds(8)));
    }

    public function test_loop_backoff_doubles_and_is_capped(): void
    {
        $this->assertSame(1500, RelayOutboxEvents::backoffMs(1500, 0));
        $this->assertSame(6000, RelayOutboxEvents::backoffMs(1500, 2));
        $this->assertSame(RelayOutboxEvents::MAX_BACKOFF_MS, RelayOutboxEvents::backoffMs(1500, 50));
    }
}
