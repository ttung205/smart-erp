<?php

namespace App\Support;

class SalesWorkflow
{
    private const TRANSITIONS = [
        'quotation' => [
            'DRAFT' => ['PENDING_APPROVAL'],
            'PENDING_APPROVAL' => ['APPROVED', 'REJECTED'],
            'REJECTED' => ['DRAFT'],
            'APPROVED' => ['CONVERTED'],
            'CONVERTED' => [],
        ],
        'order' => [
            'DRAFT' => ['PENDING_APPROVAL'],
            'PENDING_APPROVAL' => ['APPROVED', 'REJECTED'],
            'REJECTED' => ['DRAFT'],
            'APPROVED' => ['PROCESSING'],
            'PROCESSING' => ['COMPLETED'],
            'COMPLETED' => [],
            'CANCELLED' => [],
        ],
        'delivery' => [
            'PENDING' => ['APPROVED', 'REJECTED'],
            'APPROVED' => ['INVOICED', 'PARTIAL', 'DELIVERED'],
            'INVOICED' => ['PARTIAL', 'DELIVERED'],
            'PARTIAL' => ['DELIVERED'],
            'DELIVERED' => [],
            'REJECTED' => [],
        ],
    ];

    public static function canTransition(string $workflow, string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$workflow][$from] ?? [], true);
    }

    /** @return array<int, string> */
    public static function allowedTargets(string $workflow, string $from): array
    {
        return self::TRANSITIONS[$workflow][$from] ?? [];
    }
}
