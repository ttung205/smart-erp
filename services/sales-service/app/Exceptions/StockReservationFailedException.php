<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Inventory không giữ được hàng cho chứng từ: chứng từ không được tạo. */
class StockReservationFailedException extends RuntimeException
{
    /** @param  array{code?: string, message?: string}  $error */
    public function __construct(public readonly array $error)
    {
        parent::__construct($error['message'] ?? 'Không giữ được hàng tại Inventory Service.');
    }

    public static function status(?string $code): int
    {
        return match ($code) {
            'INSUFFICIENT_STOCK' => 409,
            'PRODUCT_UNRESOLVED' => 422,
            'DEPENDENCY_UNAVAILABLE' => 503,
            default => 502,
        };
    }

    public function render(): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $this->error], self::status($this->error['code'] ?? null));
    }
}
