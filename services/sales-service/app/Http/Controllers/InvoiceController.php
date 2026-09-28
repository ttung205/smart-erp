<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with('items')->orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $invoices]);
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
