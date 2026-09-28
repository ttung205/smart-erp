<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = SalesOrder::with('items')->orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function show($id)
    {
        $so = SalesOrder::with('items')->where('id', $id)->orWhere('order_number', $id)->first();
        if (!$so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }
        return response()->json(['success' => true, 'data' => $so]);
    }

    public function store(Request $request)
    {
        $randNum = rand(1000, 9999);
        $soNumber = "SO-2026-{$randNum}";

        $so = SalesOrder::create([
            'order_number' => $soNumber,
            'quotation_number' => $request->input('quotation_number'),
            'customer_id' => $request->input('customer_id'),
            'customer_name' => $request->input('customer_name', 'Khách hàng'),
            'order_date' => $request->input('order_date', now()->format('Y-m-d')),
            'total_amount' => (float)$request->input('total_amount', 0),
            'status' => 'CONFIRMED',
            'notes' => $request->input('notes'),
        ]);

        foreach ($request->input('items', []) as $it) {
            SalesOrderItem::create([
                'order_id' => $so->id,
                'sku' => $it['sku'] ?? $it['product_sku'] ?? '',
                'product_name' => $it['product_name'] ?? '',
                'quantity' => (float)($it['quantity'] ?? 1),
                'unit_price' => (float)($it['unit_price'] ?? 0),
                'subtotal' => (float)($it['subtotal'] ?? 0),
            ]);
        }

        return response()->json(['success' => true, 'data' => $so->load('items')], 201);
    }
}
