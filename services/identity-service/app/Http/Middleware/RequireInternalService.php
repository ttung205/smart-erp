<?php

namespace App\Http\Middleware;

use App\Auth\InternalServiceKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route chỉ được gọi qua một service khác (vd. purchase-service áp kết quả hỏi hàng), không gọi thẳng từ trình duyệt. */
class RequireInternalService
{
    public function __construct(private readonly InternalServiceKey $serviceKey) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->serviceKey->matches($request->header(InternalServiceKey::HEADER))) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Chỉ service nội bộ được gọi thao tác này.'],
            ], 403);
        }

        return $next($request);
    }
}
