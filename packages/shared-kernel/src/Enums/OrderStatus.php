<?php

declare(strict_types=1);

namespace SmartERP\SharedKernel\Enums;

enum OrderStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_RESERVATION = 'PENDING_RESERVATION';
    case CONFIRMED = 'CONFIRMED';
    case REJECTED = 'REJECTED';
    case PROCESSING = 'PROCESSING';
    case PARTIALLY_DELIVERED = 'PARTIALLY_DELIVERED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
