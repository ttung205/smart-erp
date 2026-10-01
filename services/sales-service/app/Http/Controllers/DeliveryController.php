<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeliveryRequest;
use App\Models\DeliveryRequestItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SalesOrder;

class DeliveryController extends Controller
{
    public function index()
    {
        $drs = DeliveryRequest::with(['items.orderItem', 'order.creator', 'order.customer'])->orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $drs]);
    }

    public function store(Request $request)
    {
        $randNum = rand(100, 999);
        $drNumber = "DR-2026-{$randNum}";

        $orderId = $request->input('order_id');
        $so = null;
        if ($orderId) {
            $so = SalesOrder::find($orderId);
        } elseif ($request->filled('order_number')) {
            $so = SalesOrder::where('order_number', $request->input('order_number'))->orWhere('id', $request->input('order_number'))->first();
            $orderId = $so?->id;
        }

        $dr = DeliveryRequest::create([
            'order_id' => $orderId,
            'request_number' => $request->input('request_number') ?: $drNumber,
            'warehouse_name' => $request->input('warehouse_name', 'Kho Tổng Miền Bắc (Hà Nội)'),
            'shipping_address' => $request->input('shipping_address') ?: $so?->shipping_address,
            'contact_phone' => $request->input('contact_phone') ?: $so?->contact?->phone,
            'request_date' => now()->format('Y-m-d'),
            'expected_delivery_date' => $request->input('expected_delivery_date'),
            'status' => 'PENDING',
        ]);

        foreach ($request->input('items', []) as $it) {
            $reqQty = (float)($it['quantity'] ?? $it['request_quantity'] ?? 1);
            $orderItemId = $it['order_item_id'] ?? null;
            if (!$orderItemId && $so) {
                $sku = $it['sku'] ?? $it['product_sku'] ?? '';
                $soItem = $so->items()->where('sku', $sku)->first();
                $orderItemId = $soItem?->id;
            }

            DeliveryRequestItem::create([
                'delivery_request_id' => $dr->id,
                'order_item_id' => $orderItemId,
                'sku' => $it['sku'] ?? $it['product_sku'] ?? '',
                'quantity' => $reqQty,
            ]);
        }

        return response()->json(['success' => true, 'data' => $dr->load('items.orderItem')], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $dr = DeliveryRequest::with('items.orderItem')->where('id', $id)->orWhere('request_number', $id)->first();
        if (!$dr) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $nextStatus = $request->input('status', 'APPROVED');
        $dr->update(['status' => $nextStatus]);

        // Nếu duyệt giao hàng thành công (APPROVED) -> tự động sinh Chứng Từ Bán Hàng (Invoice)
        if ($nextStatus === 'APPROVED') {
            $invExists = Invoice::where('delivery_request_id', $dr->id)
                ->orWhere('delivery_request_number', $dr->request_number)
                ->first();
            if (!$invExists) {
                $randNum = rand(100, 999);
                $inv = Invoice::create([
                    'invoice_number' => "INV-2026-{$randNum}",
                    'delivery_request_id' => $dr->id,
                    'delivery_request_number' => $dr->request_number,
                    'invoice_date' => now()->format('Y-m-d'),
                    'payment_terms' => 'Công nợ 30 ngày',
                    'total_amount' => $dr->items->sum(fn($it) => $it->quantity * $it->unit_price),
                    'warehouse_status' => 'DELIVERED',
                    'accounting_status' => 'UNPOSTED',
                ]);

                foreach ($dr->items as $it) {
                    InvoiceItem::create([
                        'invoice_id' => $inv->id,
                        'order_item_id' => $it->order_item_id,
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
