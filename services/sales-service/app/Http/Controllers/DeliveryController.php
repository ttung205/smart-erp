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
            $productId = $it['product_id'] ?? null;
            $sku = $it['sku'] ?? $it['product_sku'] ?? '';

            if ($orderItemId && $so) {
                $soItem = $so->items->firstWhere('id', $orderItemId);
                if ($soItem) {
                    $productId = $productId ?: $soItem->product_id;
                    $sku = $sku ?: $soItem->sku;
                }
            } elseif ($so) {
                if ($productId) {
                    $soItem = $so->items()->where('product_id', $productId)->first();
                } else {
                    $soItem = $so->items()->where('sku', $sku)->first();
                }
                $orderItemId = $soItem?->id;
                $productId = $productId ?: $soItem?->product_id;
                $sku = $sku ?: ($soItem?->sku ?? '');
            }

            if (!$productId && !empty($sku)) {
                $productId = \App\Models\Product::where('sku', $sku)->value('id');
            }

            DeliveryRequestItem::create([
                'delivery_request_id' => $dr->id,
                'order_item_id' => $orderItemId,
                'product_id' => $productId,
                'sku' => $sku,
                'quantity' => $reqQty,
            ]);
        }

        return response()->json(['success' => true, 'data' => $dr->load(['items.orderItem', 'items.product'])], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $dr = DeliveryRequest::with('items.orderItem')->where('id', $id)->orWhere('request_number', $id)->first();
        if (!$dr) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $nextStatus = $request->input('status', 'APPROVED');
        $dr->update(['status' => $nextStatus]);

        return response()->json(['success' => true, 'data' => $dr]);
    }
}
