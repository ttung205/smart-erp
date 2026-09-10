<?php

declare(strict_types=1);

namespace SmartERP\SharedKernel\Enums;

enum PurchaseOrderStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case WAITING_DELIVERY = 'WAITING_DELIVERY';
    case RECEIVING = 'RECEIVING';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
