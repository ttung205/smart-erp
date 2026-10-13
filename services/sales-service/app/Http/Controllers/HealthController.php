<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $databaseHealthy = false;
        $inventoryHealthy = false;

        try {
            DB::select('select 1');
            $databaseHealthy = true;
        } catch (\Throwable) {
            $databaseHealthy = false;
        }

        try {
            $inventoryUrl = config('services.inventory.url');
            $inventoryHealthy = Http::timeout(config('services.inventory.timeout', 3))
                ->get("{$inventoryUrl}/health")
                ->successful();
        } catch (\Throwable) {
            $inventoryHealthy = false;
        }

        $healthy = $databaseHealthy && $inventoryHealthy;

        return response()->json([
            'success' => $healthy,
            'data' => [
                'service' => 'sales-service',
                'framework' => 'Laravel 13',
                'status' => $healthy ? 'UP' : 'DEGRADED',
                'checks' => [
                    'database' => $databaseHealthy ? 'UP' : 'DOWN',
                    'inventory_service' => $inventoryHealthy ? 'UP' : 'DOWN',
                ],
                'timestamp' => now()->toIso8601String(),
            ],
        ], $healthy ? 200 : 503);
    }
}
