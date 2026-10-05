<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\DeliveryRequest;
use App\Models\DeliveryRequestItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with([
            'items.orderItem',
            'deliveryRequest.order.customer',
            'deliveryRequest.order.creator',
            'deliveryRequest.items'
        ])->orderBy('id', 'desc')->get();

        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function store(Request $request)
    {
        $randNum = rand(1000, 9999);
        $invNumber = $request->input('invoice_number') ?: "INV-2026-{$randNum}";

        $drId = $request->input('delivery_request_id');
        $drNumber = $request->input('delivery_request_number');
        $dr = null;
        if ($drId) {
            $dr = DeliveryRequest::with('order.items', 'items')->find($drId);
        } elseif ($drNumber) {
            $dr = DeliveryRequest::with('order.items', 'items')->where('request_number', $drNumber)->orWhere('id', $drNumber)->first();
            $drId = $dr?->id;
        }

        $order = $dr?->order;

        $currency = $request->input('currency') ?: ($order?->currency ?: 'VND');
        $exchangeRate = (float)($request->input('exchange_rate') ?: ($order?->exchange_rate ?: 1.0));
        $createdBy = $request->input('created_by') ?: 'admin';
        $sendImmediately = $request->boolean('send_to_warehouse', false);
        $warehouseName = $request->input('warehouse_name') ?: ($dr?->warehouse_name ?: 'Kho Tổng Miền Bắc (Hà Nội)');
        $warehouseId = $this->resolveWarehouseId($warehouseName);

        $inv = DB::transaction(function () use ($request, $invNumber, $drId, $drNumber, $dr, $order, $currency, $exchangeRate, $createdBy, $sendImmediately) {
            $inv = Invoice::create([
                'invoice_number' => $invNumber,
                'delivery_request_id' => $drId,
                'delivery_request_number' => $drNumber ?: $dr?->request_number,
                'invoice_date' => $request->input('invoice_date', now()->format('Y-m-d')),
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'created_by' => $createdBy,
                'payment_terms' => $request->input('payment_terms', 'Công nợ 30 ngày'),
                'total_amount' => (float)$request->input('total_amount', 0),
                'notes' => $request->input('notes'),
                'warehouse_status' => $sendImmediately ? 'SENT_TO_WAREHOUSE' : 'PENDING_WAREHOUSE',
                'accounting_status' => 'UNPOSTED',
            ]);

            $itemsData = $request->input('items', []);
            $calculatedTotal = 0;

            foreach ($itemsData as $it) {
                $productId = $it['product_id'] ?? null;
                $sku = $it['sku'] ?? $it['product_sku'] ?? '';
                $orderItemId = $it['order_item_id'] ?? null;
                $drItemId = $it['delivery_request_item_id'] ?? null;

                if ($orderItemId && $order) {
                    $orderItem = $order->items->firstWhere('id', $orderItemId);
                    if ($orderItem) {
                        $productId = $productId ?: $orderItem->product_id;
                        $sku = $sku ?: $orderItem->sku;
                    }
                }

                if ($drItemId && $dr) {
                    $drItem = $dr->items->firstWhere('id', $drItemId);
                    if ($drItem) {
                        $productId = $productId ?: $drItem->product_id;
                        $sku = $sku ?: $drItem->sku;
                        $orderItemId = $orderItemId ?: $drItem->order_item_id;
                    }
                }

                if (!$drItemId && $dr) {
                    $matchDrItem = null;
                    if ($productId) {
                        $matchDrItem = $dr->items->firstWhere('product_id', $productId);
                    }
                    if (!$matchDrItem && $sku) {
                        $matchDrItem = $dr->items->firstWhere('sku', $sku);
                    }
                    $drItemId = $matchDrItem?->id;
                    if (!$orderItemId) {
                        $orderItemId = $matchDrItem?->order_item_id;
                    }
                    if (!$productId && $matchDrItem) {
                        $productId = $matchDrItem->product_id;
                    }
                }

                if (!$orderItemId && $order) {
                    $matchSoItem = null;
                    if ($productId) {
                        $matchSoItem = $order->items->firstWhere('product_id', $productId);
                    }
                    if (!$matchSoItem && $sku) {
                        $matchSoItem = $order->items->firstWhere('sku', $sku);
                    }
                    $orderItemId = $matchSoItem?->id;
                    if (!$productId && $matchSoItem) {
                        $productId = $matchSoItem->product_id;
                    }
                }

                if (!$productId && !empty($sku)) {
                    $productId = Product::where('sku', $sku)->value('id');
                }

                $requestedQty = (float)($it['requested_quantity'] ?? $it['request_quantity'] ?? 0);
                if ($requestedQty <= 0 && $drItemId) {
                    $drIt = $dr?->items->firstWhere('id', $drItemId);
                    $requestedQty = (float)($drIt?->quantity ?? 0);
                }

                $qty = (float)($it['quantity'] ?? $requestedQty ?: 1);
                $unitPrice = (float)($it['unit_price'] ?? $it['price'] ?? 0);
                $calculatedTotal += ($qty * $unitPrice);

                InvoiceItem::create([
                    'invoice_id' => $inv->id,
                    'order_item_id' => $orderItemId,
                    'delivery_request_item_id' => $drItemId,
                    'product_id' => $productId,
                    'sku' => $sku,
                    'product_name' => $it['product_name'] ?? $it['designation'] ?? $sku,
                    'description' => $it['description'] ?? null,
                    'requested_quantity' => $requestedQty,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                ]);
            }

            if ($calculatedTotal > 0 && !(float)$request->input('total_amount')) {
                $inv->update(['total_amount' => $calculatedTotal]);
            }

            if ($dr) {
                $dr->update(['status' => 'INVOICED']);
            }

            return $inv;
        });

        // Tự động giữ chỗ tồn kho (Reserve Stock) cho chứng từ bán hàng
        $reservationResult = $this->reserveStockForInvoice($inv, $warehouseId);

        return response()->json([
            'success' => true,
            'data' => $inv->load('items', 'deliveryRequest.order.customer'),
            'reservation' => $reservationResult,
            'message' => "Đã tạo chứng từ {$inv->invoice_number} và gửi lệnh giữ chỗ tồn kho thành công."
        ], 201);
    }

    public function show($id)
    {
        $inv = Invoice::with([
            'items.orderItem',
            'deliveryRequest.order.customer',
            'deliveryRequest.order.creator',
            'deliveryRequest.items'
        ])->where('id', $id)->orWhere('invoice_number', $id)->first();

        if (!$inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        return response()->json(['success' => true, 'data' => $inv]);
    }

    /**
     * Đồng bộ giữ chỗ tồn kho cho chứng từ bán hàng đã tạo
     */
    public function syncReserve(Request $request, $id)
    {
        $inv = Invoice::with(['items', 'deliveryRequest'])->where('id', $id)->orWhere('invoice_number', $id)->first();
        if (!$inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $warehouseName = $request->input('warehouse_name') ?: ($inv->deliveryRequest?->warehouse_name ?: 'Kho Tổng Miền Bắc (Hà Nội)');
        $warehouseId = $this->resolveWarehouseId($warehouseName);

        $result = $this->reserveStockForInvoice($inv, $warehouseId);

        return response()->json([
            'success' => true,
            'data' => $inv,
            'reservation' => $result,
            'message' => "Đã đồng bộ giữ chỗ tồn kho cho chứng từ {$inv->invoice_number} thành công."
        ]);
    }

    /**
     * Gửi chứng từ bán hàng xuống phòng logistics (Trang warehouse-request-sale)
     */
    public function sendToWarehouse(Request $request, $id)
    {
        $inv = Invoice::where('id', $id)->orWhere('invoice_number', $id)->first();
        if (!$inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $inv->update([
            'warehouse_status' => 'SENT_TO_WAREHOUSE'
        ]);

        return response()->json([
            'success' => true,
            'data' => $inv,
            'message' => "Đã gửi chứng từ {$inv->invoice_number} xuống phòng Logistics thành công."
        ]);
    }

    /**
     * Cập nhật tiến độ kho / điều phối logistics (Soạn hàng, đóng gói, giao hàng)
     */
    public function assignLogistics(Request $request, $id)
    {
        $inv = Invoice::where('id', $id)->orWhere('invoice_number', $id)->first();
        if (!$inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $updates = [];
        if ($request->has('picker_name')) $updates['picker_name'] = $request->input('picker_name');
        if ($request->has('packer_name')) $updates['packer_name'] = $request->input('packer_name');
        if ($request->has('shipper_name')) $updates['shipper_name'] = $request->input('shipper_name');
        if ($request->has('shipping_note')) $updates['shipping_note'] = $request->input('shipping_note');
        if ($request->has('status')) $updates['warehouse_status'] = $request->input('status');

        $inv->update($updates);

        return response()->json([
            'success' => true,
            'data' => $inv,
            'message' => 'Cập nhật tiến độ điều phối kho thành công.'
        ]);
    }

    /**
     * Ghi sổ xuất kho tại phòng Logistics:
     * - Xác nhận hàng ra khỏi kho
     * - Trừ tồn thật (On-Hand) và giải phóng Reserved tại Inventory Service
     * - Cập nhật số lượng đã giao trên DeliveryRequestItems & SalesOrderItems
     * - Cập nhật status DeliveryRequest & SalesOrder (Giao đủ / Giao một phần)
     */
    public function warehousePost(Request $request, $id)
    {
        $inv = Invoice::with(['items', 'deliveryRequest.items', 'deliveryRequest.order.items'])
            ->where('id', $id)
            ->orWhere('invoice_number', $id)
            ->first();

        if (!$inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $posterName = $request->input('posted_by') ?: ($request->input('warehouse_posted_by') ?: 'Kho vận');
        $nowStr = now()->format('Y-m-d H:i:s');
        $warehouseName = $request->input('warehouse_name') ?: ($inv->deliveryRequest?->warehouse_name ?: 'Kho Tổng Miền Bắc (Hà Nội)');
        $warehouseId = $this->resolveWarehouseId($warehouseName);

        DB::transaction(function () use ($inv, $posterName, $nowStr) {
            // 1. Cập nhật Invoice
            $inv->update([
                'warehouse_status' => 'DELIVERED',
                'accounting_status' => 'POSTED',
                'warehouse_posted_at' => $nowStr,
                'warehouse_posted_by' => $posterName,
                'posted_at' => $nowStr,
                'posted_by' => $posterName,
            ]);

            // 2. Cập nhật số lượng đã giao thực tế trên từng InvoiceItem & DeliveryRequestItem & SalesOrderItem
            foreach ($inv->items as $invItem) {
                $actualDeliveredQty = (float)$invItem->quantity;
                $invItem->update(['delivered_quantity' => $actualDeliveredQty]);

                // Update DeliveryRequestItem
                if ($invItem->delivery_request_item_id) {
                    $drItem = DeliveryRequestItem::find($invItem->delivery_request_item_id);
                    if ($drItem) {
                        $drItem->increment('delivered_quantity', $actualDeliveredQty);
                    }
                } elseif ($inv->delivery_request_id) {
                    $drItem = null;
                    if ($invItem->product_id) {
                        $drItem = DeliveryRequestItem::where('delivery_request_id', $inv->delivery_request_id)
                            ->where('product_id', $invItem->product_id)
                            ->first();
                    }
                    if (!$drItem && !empty($invItem->sku)) {
                        $drItem = DeliveryRequestItem::where('delivery_request_id', $inv->delivery_request_id)
                            ->where('sku', $invItem->sku)
                            ->first();
                    }
                    if ($drItem) {
                        $drItem->increment('delivered_quantity', $actualDeliveredQty);
                    }
                }

                // Update SalesOrderItem
                if ($invItem->order_item_id) {
                    $soItem = SalesOrderItem::find($invItem->order_item_id);
                    if ($soItem) {
                        $soItem->increment('delivered_quantity', $actualDeliveredQty);
                    }
                } elseif ($inv->deliveryRequest?->order_id) {
                    $soItem = null;
                    if ($invItem->product_id) {
                        $soItem = SalesOrderItem::where('order_id', $inv->deliveryRequest->order_id)
                            ->where('product_id', $invItem->product_id)
                            ->first();
                    }
                    if (!$soItem && !empty($invItem->sku)) {
                        $soItem = SalesOrderItem::where('order_id', $inv->deliveryRequest->order_id)
                            ->where('sku', $invItem->sku)
                            ->first();
                    }
                    if ($soItem) {
                        $soItem->increment('delivered_quantity', $actualDeliveredQty);
                    }
                }
            }

            // 3. Cập nhật trạng thái DeliveryRequest (Giao đủ 100% / Giao một phần)
            if ($inv->deliveryRequest) {
                $dr = $inv->deliveryRequest->fresh(['items']);
                $totalReq = $dr->items->sum('quantity');
                $totalDelivered = $dr->items->sum('delivered_quantity');

                if ($totalDelivered >= $totalReq && $totalReq > 0) {
                    $dr->update(['status' => 'DELIVERED']);
                } elseif ($totalDelivered > 0) {
                    $dr->update(['status' => 'PARTIAL']);
                }
            }

            // 4. Cập nhật trạng thái SalesOrder (Đã giao đủ / Giao một phần)
            if ($inv->deliveryRequest?->order) {
                $so = $inv->deliveryRequest->order->fresh(['items']);
                $totalOrdered = $so->items->sum('quantity');
                $totalDeliveredSo = $so->items->sum('delivered_quantity');

                if ($totalDeliveredSo >= $totalOrdered && $totalOrdered > 0) {
                    $so->update(['status' => 'COMPLETED']);
                } elseif ($totalDeliveredSo > 0) {
                    $so->update(['status' => 'PROCESSING']);
                }
            }
        });

        // 5. Thực hiện xuất kho vật lý (Goods Issue) tại Inventory Service
        $issueResult = $this->issueStockForInvoice($inv, $warehouseId);

        return response()->json([
            'success' => true,
            'data' => $inv->fresh(['items', 'deliveryRequest.order']),
            'inventory_issue' => $issueResult,
            'message' => 'Đã ghi sổ xuất kho, trừ tồn kho vật lý và cập nhật tiến độ giao hàng thành công.'
        ]);
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
        return $this->warehousePost($request, $id);
    }

    /**
     * Map tên kho sang warehouse_id chuẩn của Inventory Service
     */
    protected function resolveWarehouseId(?string $warehouseName): int
    {
        if (!$warehouseName) return 1;
        $nameLower = mb_strtolower($warehouseName);
        if (str_contains($nameLower, 'hồ chí minh') || str_contains($nameLower, 'tp.hcm') || str_contains($nameLower, 'hcm') || str_contains($nameLower, 'nam')) {
            return 2;
        }
        if (str_contains($nameLower, 'đà nẵng') || str_contains($nameLower, 'da nang') || str_contains($nameLower, 'trung')) {
            return 3;
        }
        return 1;
    }

    /**
     * Gửi yêu cầu giữ chỗ (Reserve) tới Inventory Service (:8004)
     */
    protected function reserveStockForInvoice(Invoice $inv, int $warehouseId): array
    {
        $results = [];
        $items = $inv->items ?: [];

        foreach ($items as $item) {
            $productId = $item->product_id ?: $item->orderItem?->product_id ?: $item->deliveryRequestItem?->product_id;
            if (!$productId && !empty($item->sku)) {
                $productId = Product::where('sku', $item->sku)->value('id');
            }

            if (!$productId && !empty($item->sku)) {
                // Thử tìm trong bảng products
                $prod = DB::table('products')->where('sku', $item->sku)->first();
                $productId = $prod?->id;
            }

            if ($productId && (float)$item->quantity > 0) {
                try {
                    $response = Http::timeout(2)->post('http://127.0.0.1:8004/api/v1/inventory/stocks/reserve', [
                        'order_id' => $inv->invoice_number,
                        'warehouse_id' => $warehouseId,
                        'product_id' => (int)$productId,
                        'quantity' => (float)$item->quantity
                    ]);

                    $results[] = [
                        'sku' => $item->sku,
                        'product_id' => $productId,
                        'quantity' => (float)$item->quantity,
                        'success' => $response->successful(),
                        'data' => $response->json()
                    ];
                } catch (\Throwable $e) {
                    Log::warning("[InvoiceController] Lỗi gọi Inventory Service Reserve: " . $e->getMessage());
                    $results[] = [
                        'sku' => $item->sku,
                        'product_id' => $productId,
                        'quantity' => (float)$item->quantity,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Gửi yêu cầu xuất kho thực tế (Issue) tới Inventory Service (:8004)
     */
    protected function issueStockForInvoice(Invoice $inv, int $warehouseId): array
    {
        $results = [];
        $items = $inv->items ?: [];
        $orderNumber = $inv->deliveryRequest?->order_number ?: $inv->invoice_number;

        foreach ($items as $item) {
            $productId = $item->product_id ?: $item->orderItem?->product_id ?: $item->deliveryRequestItem?->product_id;
            if (!$productId && !empty($item->sku)) {
                $productId = Product::where('sku', $item->sku)->value('id');
            }

            if (!$productId && !empty($item->sku)) {
                $prod = DB::table('products')->where('sku', $item->sku)->first();
                $productId = $prod?->id;
            }

            if ($productId && (float)$item->quantity > 0) {
                try {
                    $response = Http::timeout(2)->post('http://127.0.0.1:8004/api/v1/inventory/stocks/issue', [
                        'reference_doc' => $inv->invoice_number,
                        'order_id' => $orderNumber,
                        'warehouse_id' => $warehouseId,
                        'product_id' => (int)$productId,
                        'quantity' => (float)$item->quantity,
                        'notes' => "Xuất kho bán hàng theo chứng từ {$inv->invoice_number}"
                    ]);

                    $results[] = [
                        'sku' => $item->sku,
                        'product_id' => $productId,
                        'quantity' => (float)$item->quantity,
                        'success' => $response->successful(),
                        'data' => $response->json()
                    ];
                } catch (\Throwable $e) {
                    Log::warning("[InvoiceController] Lỗi gọi Inventory Service Issue: " . $e->getMessage());
                    $results[] = [
                        'sku' => $item->sku,
                        'product_id' => $productId,
                        'quantity' => (float)$item->quantity,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return $results;
    }
}
