<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with(['items', 'deliveryRequest.order.customer'])->orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function store(Request $request)
    {
        $randNum = rand(100, 999);
        $invNumber = $request->input('invoice_number') ?? "INV-2026-{$randNum}";

        $drId = $request->input('delivery_request_id');
        $drNumber = $request->input('delivery_request_number');
        if (!$drId && $drNumber) {
            $dr = \App\Models\DeliveryRequest::where('request_number', $drNumber)->orWhere('id', $drNumber)->first();
            $drId = $dr?->id;
        }

        $inv = Invoice::create([
            'invoice_number' => $invNumber,
            'delivery_request_id' => $drId,
            'delivery_request_number' => $drNumber,
            'invoice_date' => $request->input('invoice_date', now()->format('Y-m-d')),
            'payment_terms' => $request->input('payment_terms', 'Công nợ 30 ngày'),
            'total_amount' => (float)$request->input('total_amount', 0),
            'warehouse_status' => $request->input('warehouse_status', 'PICKING'),
            'accounting_status' => 'UNPOSTED',
        ]);

        foreach ($request->input('items', []) as $it) {
            InvoiceItem::create([
                'invoice_id' => $inv->id,
                'sku' => $it['sku'] ?? $it['product_sku'] ?? '',
                'product_name' => $it['product_name'] ?? $it['designation'] ?? '',
                'quantity' => (float)($it['quantity'] ?? 1),
                'unit_price' => (float)($it['unit_price'] ?? $it['price'] ?? 0),
            ]);
        }

        return response()->json(['success' => true, 'data' => $inv->load('items')], 201);
    }

    public function show($id)
    {
        $inv = Invoice::with('items')->where('id', $id)->orWhere('invoice_number', $id)->first();
        if (!$inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }
        return response()->json(['success' => true, 'data' => $inv]);
    }

    public function updateWarehouseStatus(Request $request, $id)
    {
        $inv = Invoice::where('id', $id)->orWhere('invoice_number', $id)->first();
        if (!$inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $inv->update([
            'warehouse_status' => $request->input('status', 'DELIVERED')
        ]);

        return response()->json(['success' => true, 'data' => $inv]);
    }

    public function postAccounting(Request $request, $id)
    {
        $inv = Invoice::where('id', $id)->orWhere('invoice_number', $id)->first();
        if (!$inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $accountant = $request->input('posted_by', 'kt_truong');
        $inv->update([
            'accounting_status' => 'POSTED',
            'posted_at' => now()->format('d/m/Y H:i'),
            'posted_by' => $accountant,
        ]);

        return response()->json(['success' => true, 'data' => $inv, 'message' => 'Đã ghi sổ kế toán thành công.']);
    }
}
