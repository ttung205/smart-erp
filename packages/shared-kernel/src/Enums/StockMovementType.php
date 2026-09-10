<?php

declare(strict_types=1);

namespace SmartERP\SharedKernel\Enums;

enum StockMovementType: string
{
    case PO_RECEIPT = 'PO_RECEIPT';
    case SO_ISSUE = 'SO_ISSUE';
    case STOCK_TRANSFER_OUT = 'STOCK_TRANSFER_OUT';
    case STOCK_TRANSFER_IN = 'STOCK_TRANSFER_IN';
    case ADJUSTMENT = 'ADJUSTMENT';
    case RETURN = 'RETURN';
}
