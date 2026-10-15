<?php

namespace App\Http\Controllers;

use App\Models\CustomerReceivable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReceivableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CustomerReceivable::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('invoice_series', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_tax_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('invoice_date', '>=', $request->date('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('invoice_date', '<=', $request->date('to_date'));
        }

        $summary = [
            'total_receivable' => (float) (clone $query)->sum('original_amount'),
            'total_paid' => (float) (clone $query)->sum('paid_amount'),
            'total_outstanding' => (float) (clone $query)->sum('outstanding_amount'),
            'overdue_outstanding' => (float) (clone $query)
                ->where('outstanding_amount', '>', 0)
                ->whereDate('due_date', '<', today())
                ->sum('outstanding_amount'),
        ];

        $this->applyStatusFilter($query, $request->input('status'));

        $receivables = $query
            ->orderByRaw('CASE WHEN due_date < ? AND outstanding_amount > 0 THEN 0 ELSE 1 END', [today()->toDateString()])
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json([
            'success' => true,
            'data' => $receivables->items(),
            'summary' => $summary,
            'total' => $receivables->total(),
            'meta' => [
                'current_page' => $receivables->currentPage(),
                'last_page' => $receivables->lastPage(),
                'per_page' => $receivables->perPage(),
            ],
        ]);
    }

    private function applyStatusFilter(Builder $query, mixed $status): void
    {
        match ($status) {
            'PAID' => $query->where('outstanding_amount', '<=', 0),
            'PARTIALLY_PAID' => $query->where('paid_amount', '>', 0)->where('outstanding_amount', '>', 0),
            'OVERDUE' => $query->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', today()),
            'OPEN' => $query->where('paid_amount', '<=', 0)
                ->where('outstanding_amount', '>', 0)
                ->where(function (Builder $builder): void {
                    $builder->whereNull('due_date')->orWhereDate('due_date', '>=', today());
                }),
            default => null,
        };
    }
}
