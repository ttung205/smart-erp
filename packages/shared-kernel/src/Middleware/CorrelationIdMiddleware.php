<?php

declare(strict_types=1);

namespace SmartERP\SharedKernel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    public const HEADER_NAME = 'X-Correlation-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header(self::HEADER_NAME);

        if (empty($correlationId)) {
            $correlationId = (string) strtolower((string) \Illuminate\Support\Str::uuid());
            $request->headers->set(self::HEADER_NAME, $correlationId);
        }

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER_NAME, $correlationId);

        return $response;
    }
}
