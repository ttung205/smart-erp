<?php

declare(strict_types=1);

namespace SmartERP\SharedKernel\Contracts;

interface DomainEventInterface
{
    public function getEventId(): string;
    public function getEventType(): string;
    public function getAggregateType(): string;
    public function getAggregateId(): string;
    public function getTimestamp(): string;
    public function getVersion(): string;
    public function getCorrelationId(): ?string;
    public function getProducer(): string;
    public function getPayload(): array;
    public function toArray(): array;
    public function toJson(): string;
}
