<?php

namespace App\Http\Controllers;

class HealthController extends Controller
{
    public function check()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'service' => 'sales-service',
                'framework' => 'Laravel 12 / 13',
                'status' => 'UP',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
