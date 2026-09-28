<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeliveryRequest;
use App\Models\DeliveryRequestItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;

class DeliveryController extends Controller
{
    public function index()
    {
        $drs = DeliveryRequest::with('items')->orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $drs]);
    }

    public function store(Request $request)
    {
        $randNum = rand(100, 999);
        $drNumber = "DR-2026-{$randNum}";

        $dr = DeliveryRequest::create([
            'request_number' => $drNumber,
            'order_number' => $request->input('order_number') ?? $request->input('order_id'),
            'customer_name' => $request->input('customer_name', 'Khách hàng'),
            'warehouse_name' => $request->input('warehouse_name', 'Kho Tổng Miền Bắc (Hà Nội)'),
            'shipping_address' => $request->input('shipping_address'),
            'contact_phone' => $request->input('contact_phone'),
            'request_date' => now()->format('Y-m-d'),
            'expected_delivery_date' => $request->input('expected_delivery_date'),
            'status' => 'PENDING',
        ]);

        foreach ($request->input('items', []) as $it) {
            DeliveryRequestItem::create([
                'delivery_request_id' => $dr->id,
                'sku' => $it['sku'] ?? $it['product_sku'] ?? '',
                'product_name' => $it['product_name'] ?? '',
                'quantity' => (float)($it['quantity'] ?? 1),
                'unit_price' => (float)($it['unit_price'] ?? 0),
            ]);
        }

        return response()->json(['success' => true, 'data' => $dr->load('items')], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $dr = DeliveryRequest::with('items')->where('id', $id)->orWhere('request_number', $id)->first();
        if (!$dr) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $nextStatus = $request->input('status', 'APPROVED');
        $dr->update(['status' => $nextStatus]);

        // Nếu duyệt giao hàng thành công (APPROVED) -> tự động sinh Chứng Từ Bán Hàng (Invoice)
        if ($nextStatus === 'APPROVED') {
            $invExists = Invoice::where('delivery_request_number', $dr->request_number)->first();
            if (!$invExists) {
                $randNum = rand(100, 999);
                $inv = Invoice::create([
                    'invoice_number' => "INV-2026-{$randNum}",
                    'delivery_request_number' => $dr->request_number,
                    'order_number' => $dr->order_number,
                    'customer_name' => $dr->customer_name,
                    'warehouse_name' => $dr->warehouse_name,
                    'invoice_date' => now()->format('Y-m-d'),
                    'payment_terms' => 'Công nợ 30 ngày',
                    'total_amount' => $dr->items->sum(fn($it) => $it->quantity * $it->unit_price),
                    'warehouse_status' => 'DELIVERED',
                    'accounting_status' => 'UNPOSTED',
                ]);

                foreach ($dr->items as $it) {
                    InvoiceItem::create([
                        'invoice_id' => $inv->id,
                        'sku' => $it->sku,
                        'product_name' => $it->product_name,
                        'quantity' => $it->quantity,
                        'unit_price' => $it->unit_price,
                    ]);
                }
            }
        }

        return response()->json(['success' => true, 'data' => $dr]);
    }
}
