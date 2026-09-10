<?php

declare(strict_types=1);

namespace SmartERP\SharedKernel\Events;

use SmartERP\SharedKernel\Contracts\DomainEventInterface;
use DateTimeImmutable;
use DateTimeInterface;

abstract class BaseDomainEvent implements DomainEventInterface
{
    protected string $eventId;
    protected string $eventType;
    protected string $aggregateType;
    protected string $aggregateId;
    protected string $timestamp;
    protected string $version;
    protected ?string $correlationId;
    protected string $producer;
    protected array $payload;

    public function __construct(
        string $aggregateType,
        string $aggregateId,
        string $producer,
        array $payload,
        ?string $correlationId = null,
        ?string $eventId = null,
        ?string $timestamp = null,
        string $version = '1.0'
    ) {
        $this->eventId = $eventId ?? $this->generateUuid();
        $this->eventType = (new \ReflectionClass($this))->getShortName();
        $this->aggregateType = $aggregateType;
        $this->aggregateId = $aggregateId;
        $this->producer = $producer;
        $this->payload = $payload;
        $this->correlationId = $correlationId;
        $this->timestamp = $timestamp ?? (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
        $this->version = $version;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getProducer(): string
    {
        return $this->producer;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'timestamp' => $this->timestamp,
            'version' => $this->version,
            'correlation_id' => $this->correlationId,
            'producer' => $this->producer,
            'payload' => $this->payload,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            aggregateType: $data['aggregate_type'] ?? 'Unknown',
            aggregateId: (string)($data['aggregate_id'] ?? ''),
            producer: $data['producer'] ?? 'unknown-service',
            payload: $data['payload'] ?? [],
            correlationId: $data['correlation_id'] ?? null,
            eventId: $data['event_id'] ?? null,
            timestamp: $data['timestamp'] ?? null,
            version: $data['version'] ?? '1.0'
        );

        return $instance;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
