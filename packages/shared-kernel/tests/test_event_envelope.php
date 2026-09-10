<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Contracts/DomainEventInterface.php';
require_once __DIR__ . '/../src/Events/BaseDomainEvent.php';
require_once __DIR__ . '/../src/Enums/OrderStatus.php';
require_once __DIR__ . '/../src/Enums/StockMovementType.php';

use SmartERP\SharedKernel\Events\BaseDomainEvent;
use SmartERP\SharedKernel\Enums\OrderStatus;
use SmartERP\SharedKernel\Enums\StockMovementType;

class SalesOrderCreatedEvent extends BaseDomainEvent
{
    public function __construct(string $orderId, int $customerId, array $items, ?string $correlationId = null)
    {
        parent::__construct(
            aggregateType: 'SalesOrder',
            aggregateId: $orderId,
            producer: 'sales-service',
            payload: [
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'status' => OrderStatus::PENDING_RESERVATION->value,
                'items' => $items,
            ],
            correlationId: $correlationId
        );
    }
}

// 1. Instantiate test event
$event = new SalesOrderCreatedEvent(
    orderId: 'SO-2026-TEST-001',
    customerId: 101,
    items: [
        [
            'product_id' => 201,
            'warehouse_id' => 1,
            'quantity' => 50,
            'unit_price' => 2500000.0,
        ]
    ],
    correlationId: 'req_trace_abc123'
);

// 2. Validate properties
assert(!empty($event->getEventId()), 'Event ID must not be empty');
assert($event->getEventType() === 'SalesOrderCreatedEvent', 'Event type must match class short name');
assert($event->getAggregateType() === 'SalesOrder');
assert($event->getAggregateId() === 'SO-2026-TEST-001');
assert($event->getCorrelationId() === 'req_trace_abc123');
assert($event->getProducer() === 'sales-service');
assert($event->getPayload()['status'] === 'PENDING_RESERVATION');

// 3. Validate JSON serialization
$json = $event->toJson();
$decoded = json_decode($json, true);

assert(isset($decoded['event_id']));
assert(isset($decoded['timestamp']));
assert($decoded['aggregate_type'] === 'SalesOrder');
assert($decoded['payload']['items'][0]['quantity'] === 50);

// 4. Validate Enum values
assert(StockMovementType::PO_RECEIPT->value === 'PO_RECEIPT');
assert(StockMovementType::SO_ISSUE->value === 'SO_ISSUE');

echo "CONTRACT VERIFICATION SUCCESS: All BaseDomainEvent and Enums validated successfully!\n";
echo "Generated Envelope:\n" . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
