<?php

namespace App\Http\Controllers;

use App\Models\CustomerReceivable;
use App\Services\GeneralLedgerService;
use App\Services\ReceivableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReceivableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CustomerReceivable::with('account')->orderByDesc('invoice_date')->orderByDesc('id');
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn ($item) => $item->where('invoice_number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('customer_tax_code', 'like', "%{$search}%"));
        }
        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if ($status === 'OVERDUE') {
                $query->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', today());
            } else {
                $query->where('status', $status);
            }
        }
        $receivables = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));
        $base = CustomerReceivable::query();

        return response()->json([
            'success' => true, 'data' => $receivables->items(), 'total' => $receivables->total(),
            'summary' => [
                'total_receivable' => (float) (clone $base)->sum('original_amount'),
                'total_paid' => (float) (clone $base)->sum('paid_amount'),
                'total_outstanding' => (float) (clone $base)->where('outstanding_amount', '>', 0)->sum('outstanding_amount'),
                'total_credit' => -(float) (clone $base)->where('outstanding_amount', '<', 0)->sum('outstanding_amount'),
                'overdue_outstanding' => (float) (clone $base)->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', today())->sum('outstanding_amount'),
            ],
            'meta' => ['current_page' => $receivables->currentPage(), 'last_page' => $receivables->lastPage(), 'per_page' => $receivables->perPage()],
        ]);
    }

    public function sync(Request $request, ReceivableService $service, GeneralLedgerService $ledger): JsonResponse
    {
        $data = $request->validate([
            'vat_invoice_id' => ['required', 'integer'], 'sales_invoice_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'], 'customer_name' => ['required', 'string'],
            'customer_tax_code' => ['nullable', 'string'], 'invoice_number' => ['required', 'string'],
            'invoice_series' => ['nullable', 'string'], 'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'], 'currency' => ['nullable', 'string'],
            'total_amount' => ['required', 'numeric', 'gt:0'],
            'subtotal' => ['nullable', 'numeric', 'gte:0'], 'vat_amount' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $data['subtotal'] ??= $data['total_amount'];
        $data['vat_amount'] ??= 0;
        if (abs((float) $data['subtotal'] + (float) $data['vat_amount'] - (float) $data['total_amount']) > 0.01) {
            return response()->json(['success' => false, 'message' => 'Tiền hàng và thuế không khớp tổng hóa đơn.'], 422);
        }

        return response()->json(['success' => true, 'data' => $service->syncVatInvoice($data, $ledger)], 201);
    }
}
