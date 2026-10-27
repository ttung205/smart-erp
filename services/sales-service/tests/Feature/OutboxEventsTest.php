<?php

namespace Tests\Feature;

use App\Messaging\RabbitMqPublisher;
use App\Models\OutboxEvent;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboxEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_approval_flow_records_events_in_outbox(): void
    {
        $quotation = Quotation::create([
            'quotation_number' => 'QT-OUTBOX-001',
            'customer_name' => 'Khách hàng A',
            'quotation_date' => '2026-10-15',
            'status' => 'DRAFT',
            'currency' => 'VND',
            'exchange_rate' => 1,
            'total_amount' => 1100,
            'created_by' => 'Người tạo',
            'created_by_id' => 7,
        ]);
        $headers = $this->jwtHeaders(['quotation.update', 'quotation.approve']);

        $this->withHeaders($headers + ['X-Correlation-ID' => 'corr-test-1'])
            ->postJson("/api/quotations/{$quotation->id}/submit-approval")->assertOk();
        $this->withHeaders($headers)->postJson("/api/quotations/{$quotation->id}/approve")->assertOk();

        $events = OutboxEvent::orderBy('id')->get();
        $this->assertSame(['sales.quotation.submitted', 'sales.quotation.approved'], $events->pluck('routing_key')->all());

        $envelope = json_decode($events[0]->payload, true);
        $this->assertSame('QuotationSubmitted', $envelope['event_type']);
        $this->assertSame($events[0]->event_id, $envelope['event_id']);
        $this->assertSame('sales-service', $envelope['producer']);
        $this->assertSame('corr-test-1', $envelope['correlation_id']);
        $this->assertSame('QT-OUTBOX-001', $envelope['payload']['quotation_number']);
        $this->assertSame(7, $envelope['payload']['created_by_id']);
        $this->assertSame(['id' => 42, 'name' => 'Test User'], $envelope['payload']['actor']);
    }

    public function test_failed_transition_does_not_record_event(): void
    {
        $order = SalesOrder::create(['order_number' => 'OD-OUTBOX-001', 'order_date' => '2026-10-15', 'status' => 'DRAFT']);

        $this->withHeaders($this->jwtHeaders(['order.approve']))
            ->postJson("/api/orders/{$order->id}/approve")->assertStatus(409);

        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_relay_marks_events_published_and_records_failures(): void
    {
        $order = SalesOrder::create(['order_number' => 'OD-OUTBOX-002', 'order_date' => '2026-10-15', 'status' => 'DRAFT', 'created_by' => 9]);
        $this->withHeaders($this->jwtHeaders(['order.update']))
            ->postJson("/api/orders/{$order->id}/submit-approval")->assertOk();

        $failing = $this->mock(RabbitMqPublisher::class);
        $failing->shouldReceive('publish')->once()->andThrow(new \RuntimeException('broker down'));
        $failing->shouldReceive('close');
        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();
        $this->assertDatabaseHas('outbox_events', ['status' => 'FAILED', 'retry_count' => 1, 'last_error' => 'broker down']);
        $this->travel(3)->seconds();

        $publisher = $this->mock(RabbitMqPublisher::class);
        $publisher->shouldReceive('publish')->once()->withArgs(fn ($key, $body, $id) => $key === 'sales.order.submitted'
            && json_decode($body, true)['payload']['created_by_id'] === 9);
        $publisher->shouldReceive('close');
        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();
        $this->assertDatabaseHas('outbox_events', ['status' => 'PUBLISHED', 'routing_key' => 'sales.order.submitted']);
    }
}
