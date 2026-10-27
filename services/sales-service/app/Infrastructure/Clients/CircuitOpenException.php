<?php

namespace App\Infrastructure\Clients;

use Illuminate\Http\Client\ConnectionException;

/** Circuit breaker đang mở: không gọi sang service, coi như service không phản hồi. */
class CircuitOpenException extends ConnectionException
{
    public function __construct(public readonly string $service)
    {
        parent::__construct("{$service}: circuit breaker đang mở, tạm ngừng gọi");
    }
}
