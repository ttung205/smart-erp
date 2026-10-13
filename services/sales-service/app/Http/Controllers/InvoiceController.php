<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\DeliveryRequest;
use App\Models\DeliveryRequestItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SalesAttachment;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\WarehouseDispatch;
use App\Services\RevenueReportService;
use App\Services\SaleReportService;
use App\Support\DocumentNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::with([
            'items.orderItem',
            'deliveryRequest.order.customer',
            'deliveryRequest.order.creator',
            'deliveryRequest.items',
            'warehouseDispatch.picker',
            'warehouseDispatch.packer',
            'warehouseDispatch.shipper',
            'warehouseDispatch.postedBy',
        ])->orderByDesc('id')->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json([
            'success' => true,
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
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

        if (! $dr || ! in_array($dr->status, ['APPROVED', 'PARTIAL'], true)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_DELIVERY_STATUS', 'message' => 'Chỉ có thể tạo chứng từ cho yêu cầu giao hàng đã duyệt.'],
            ], 409);
        }

        $currency = $request->input('currency') ?: ($order?->currency ?: 'VND');
        $exchangeRate = (float) ($request->input('exchange_rate') ?: ($order?->exchange_rate ?: 1.0));
        $actor = $request->attributes->get('actor');
        $createdBy = $actor->name;
        $sendImmediately = $request->boolean('send_to_warehouse', false);
        $warehouseName = $request->input('warehouse_name') ?: ($dr?->warehouse_name ?: 'Kho Tổng Miền Bắc (Hà Nội)');
        $warehouseId = $this->resolveWarehouseId($warehouseName);

        $inv = DB::transaction(function () use ($request, $drId, $drNumber, $dr, $order, $currency, $exchangeRate, $createdBy, $sendImmediately) {
            $invNumber = $request->input('invoice_number') ?: DocumentNumberGenerator::next(
                'invoice-'.now()->format('Y'),
                'INV-'.now()->format('Y').'-',
                6
            );
            $inv = Invoice::create([
                'invoice_number' => $invNumber,
                'delivery_request_id' => $drId,
                'delivery_request_number' => $drNumber ?: $dr?->request_number,
                'invoice_date' => $request->input('invoice_date', now()->format('Y-m-d')),
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'created_by' => $createdBy,
                'payment_terms' => $request->input('payment_terms', 'Công nợ 30 ngày'),
                'total_amount' => (float) $request->input('total_amount', 0),
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
                $drItem = null;

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

                if (! $drItemId || ! isset($drItem) || ! $drItem) {
                    abort(422, 'Mặt hàng không thuộc yêu cầu giao hàng đã chọn.');
                }

                if ($orderItemId !== $drItem->order_item_id) {
                    abort(422, 'Mặt hàng đơn bán không khớp với yêu cầu giao hàng.');
                }

                if (! $drItemId && $dr) {
                    $matchDrItem = null;
                    if ($productId) {
                        $matchDrItem = $dr->items->firstWhere('product_id', $productId);
                    }
                    if (! $matchDrItem && $sku) {
                        $matchDrItem = $dr->items->firstWhere('sku', $sku);
                    }
                    $drItemId = $matchDrItem?->id;
                    if (! $orderItemId) {
                        $orderItemId = $matchDrItem?->order_item_id;
                    }
                    if (! $productId && $matchDrItem) {
                        $productId = $matchDrItem->product_id;
                    }
                }

                if (! $orderItemId && $order) {
                    $matchSoItem = null;
                    if ($productId) {
                        $matchSoItem = $order->items->firstWhere('product_id', $productId);
                    }
                    if (! $matchSoItem && $sku) {
                        $matchSoItem = $order->items->firstWhere('sku', $sku);
                    }
                    $orderItemId = $matchSoItem?->id;
                    if (! $productId && $matchSoItem) {
                        $productId = $matchSoItem->product_id;
                    }
                }

                if (! $productId && ! empty($sku)) {
                    $productId = Product::where('sku', $sku)->value('id');
                }

                $requestedQty = (float) ($it['requested_quantity'] ?? $it['request_quantity'] ?? 0);
                if ($requestedQty <= 0 && $drItemId) {
                    $drIt = $dr?->items->firstWhere('id', $drItemId);
                    $requestedQty = (float) ($drIt?->quantity ?? 0);
                }

                $qty = (float) ($it['quantity'] ?? $requestedQty ?: 1);
                $remainingQty = max(0, (float) $drItem->quantity - (float) $drItem->delivered_quantity);
                if ($qty > $remainingQty) {
                    abort(422, "Số lượng chứng từ của SKU {$sku} vượt quá số lượng còn phải giao {$remainingQty}.");
                }
                $unitPrice = (float) ($it['unit_price'] ?? $it['price'] ?? 0);
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

            $inv->update(['total_amount' => $calculatedTotal]);

            if ($dr) {
                $dr->update(['status' => 'INVOICED']);
            }

            // Liên kết tệp đính kèm nếu có
            if ($request->has('attachment_ids')) {
                SalesAttachment::whereIn('id', (array) $request->input('attachment_ids'))
                    ->whereNull('attachable_id')
                    ->where('uploaded_by', $request->attributes->get('actor')->name)
                    ->update([
                        'attachable_id' => $inv->id,
                        'attachable_type' => 'invoice',
                    ]);
            }

            return $inv;
        });

        // Tự động giữ chỗ tồn kho (Reserve Stock) cho chứng từ bán hàng
        $reservationResult = $this->reserveStockForInvoice($inv, $warehouseId);
        $reservationSucceeded = count($reservationResult) === $inv->items()->count()
            && collect($reservationResult)->every(fn (array $result): bool => $result['success'] === true);
        $hasReservedItems = collect($reservationResult)->contains(fn (array $result): bool => $result['success'] === true);
        $inv->update([
            'inventory_reservation_status' => $reservationSucceeded ? 'RESERVED' : ($hasReservedItems ? 'PARTIAL' : 'FAILED'),
            'inventory_reservation_error' => $reservationSucceeded ? null : json_encode($reservationResult, JSON_UNESCAPED_UNICODE),
            'inventory_reserved_at' => $reservationSucceeded ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $inv->load('items', 'deliveryRequest.order.customer', 'attachments'),
            'reservation' => $reservationResult,
            'message' => $reservationSucceeded
                ? "Đã tạo chứng từ {$inv->invoice_number} và giữ chỗ tồn kho thành công."
                : "Đã tạo chứng từ {$inv->invoice_number}, nhưng giữ chỗ tồn kho thất bại. Vui lòng kiểm tra và thử đồng bộ lại.",
        ], 201);
    }

    public function show($id)
    {
        $inv = Invoice::with([
            'items.orderItem',
            'deliveryRequest.order.customer',
            'deliveryRequest.order.creator',
            'deliveryRequest.items',
            'attachments',
        ])->where('id', $id)->orWhere('invoice_number', $id)->first();

        if (! $inv) {
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
        if (! $inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        if ($inv->inventory_reservation_status === 'RESERVED') {
            return response()->json([
                'success' => true,
                'data' => $inv,
                'reservation' => [],
                'message' => 'Chứng từ đã được giữ chỗ tồn kho trước đó.',
            ]);
        }
        if ($inv->inventory_reservation_status === 'PARTIAL') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PARTIAL_RESERVATION_REQUIRES_RECONCILIATION',
                    'message' => 'Một phần hàng đã được giữ chỗ. Cần đối soát Inventory trước khi thử lại để tránh giữ chỗ hai lần.',
                ],
            ], 409);
        }

        $warehouseName = $request->input('warehouse_name') ?: ($inv->deliveryRequest?->warehouse_name ?: 'Kho Tổng Miền Bắc (Hà Nội)');
        $warehouseId = $this->resolveWarehouseId($warehouseName);

        $result = $this->reserveStockForInvoice($inv, $warehouseId);
        $succeeded = count($result) === $inv->items()->count()
            && collect($result)->every(fn (array $item): bool => $item['success'] === true);
        $hasReservedItems = collect($result)->contains(fn (array $item): bool => $item['success'] === true);
        $inv->update([
            'inventory_reservation_status' => $succeeded ? 'RESERVED' : ($hasReservedItems ? 'PARTIAL' : 'FAILED'),
            'inventory_reservation_error' => $succeeded ? null : json_encode($result, JSON_UNESCAPED_UNICODE),
            'inventory_reserved_at' => $succeeded ? now() : null,
        ]);

        return response()->json([
            'success' => $succeeded,
            'data' => $inv,
            'reservation' => $result,
            'message' => $succeeded
                ? "Đã đồng bộ giữ chỗ tồn kho cho chứng từ {$inv->invoice_number} thành công."
                : 'Không thể giữ chỗ đầy đủ tại Inventory Service.',
        ], $succeeded ? 200 : 502);
    }

    /**
     * Gửi chứng từ bán hàng xuống phòng logistics (Trang warehouse-request-sale)
     */
    public function sendToWarehouse(Request $request, $id)
    {
        $inv = Invoice::where('id', $id)->orWhere('invoice_number', $id)->first();
        if (! $inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }
        if ($inv->inventory_reservation_status !== 'RESERVED') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'STOCK_NOT_RESERVED', 'message' => 'Chứng từ chưa giữ chỗ tồn kho thành công.'],
            ], 409);
        }
        if ($inv->warehouse_status !== 'PENDING_WAREHOUSE') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_WAREHOUSE_STATUS', 'message' => 'Chứng từ đã được gửi xuống kho trước đó.'],
            ], 409);
        }

        $inv->update([
            'warehouse_status' => 'SENT_TO_WAREHOUSE',
        ]);

        WarehouseDispatch::updateOrCreate(
            ['invoice_id' => $inv->id],
            [
                'invoice_number' => $inv->invoice_number,
                'delivery_request_id' => $inv->delivery_request_id,
                'delivery_request_number' => $inv->delivery_request_number,
                'order_id' => $inv->order_id,
                'order_number' => $inv->order_number,
                'customer_name' => $inv->customer_name,
                'warehouse_name' => $inv->warehouse_name,
                'status' => 'SENT_TO_WAREHOUSE',
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $inv->fresh(['warehouseDispatch.picker', 'warehouseDispatch.packer', 'warehouseDispatch.shipper']),
            'message' => "Đã gửi chứng từ {$inv->invoice_number} xuống phòng Logistics thành công.",
        ]);
    }

    /**
     * Cập nhật tiến độ kho / điều phối logistics (Soạn hàng, đóng gói, giao hàng)
     */
    public function assignLogistics(Request $request, $id)
    {
        $inv = Invoice::where('id', $id)->orWhere('invoice_number', $id)->first();
        if (! $inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $pickerId = $request->input('picker_id') ?: null;
        $pickerName = $request->input('picker_name') ?: null;
        if ($pickerId && ! $pickerName) {
            $pickerName = User::find($pickerId)?->name;
        }

        $packerId = $request->input('packer_id') ?: null;
        $packerName = $request->input('packer_name') ?: null;
        if ($packerId && ! $packerName) {
            $packerName = User::find($packerId)?->name;
        }

        $shippingMethod = $request->input('shipping_method', 'CUSTOMER_PICKUP');
        $shipperId = $request->input('shipper_id') ?: null;
        $shipperName = $request->input('shipper_name') ?: null;
        if ($shippingMethod === 'WAREHOUSE_DELIVERY' && $shipperId && ! $shipperName) {
            $shipperName = User::find($shipperId)?->name;
        }

        $shippingNote = $request->input('shipping_note');
        $validated = $request->validate([
            'picker_id' => ['nullable', 'integer'],
            'packer_id' => ['nullable', 'integer'],
            'shipper_id' => ['nullable', 'integer'],
            'shipping_method' => ['required', 'in:CUSTOMER_PICKUP,WAREHOUSE_DELIVERY,THIRD_PARTY'],
            'status' => ['nullable', 'in:SENT_TO_WAREHOUSE,PROCESSING,PROCESSED,READY_TO_SHIP'],
            'shipping_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $status = $validated['status'] ?? 'PROCESSED';

        // 1. Cập nhật bảng riêng warehouse_dispatches
        $dispatch = WarehouseDispatch::updateOrCreate(
            ['invoice_id' => $inv->id],
            [
                'invoice_number' => $inv->invoice_number,
                'delivery_request_id' => $inv->delivery_request_id,
                'delivery_request_number' => $inv->delivery_request_number,
                'order_id' => $inv->order_id,
                'order_number' => $inv->order_number,
                'customer_name' => $inv->customer_name,
                'warehouse_name' => $inv->warehouse_name,
                'status' => $status,
                'picker_id' => $pickerId,
                'picker_name' => $pickerName,
                'packer_id' => $packerId,
                'packer_name' => $packerName,
                'shipping_method' => $shippingMethod,
                'shipper_id' => $shippingMethod === 'WAREHOUSE_DELIVERY' ? $shipperId : null,
                'carrier_name' => $shipperName,
                'shipping_note' => $shippingNote,
            ]
        );

        // 2. Cập nhật Invoice snapshot
        $inv->update([
            'warehouse_status' => $status,
            'shipping_method' => $shippingMethod,
            'picker_name' => $pickerName,
            'packer_name' => $packerName,
            'shipper_name' => $shipperName,
            'shipping_note' => $shippingNote,
        ]);

        return response()->json([
            'success' => true,
            'data' => $inv->fresh(['warehouseDispatch.picker', 'warehouseDispatch.packer', 'warehouseDispatch.shipper']),
            'message' => 'Cập nhật tiến độ điều phối kho thành công.',
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

        if (! $inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        if ($inv->accounting_status === 'POSTED' || $inv->inventory_issue_status === 'ISSUED') {
            return response()->json([
                'success' => true,
                'data' => $inv,
                'inventory_issue' => [],
                'message' => 'Chứng từ đã được ghi sổ trước đó; không thực hiện xuất kho lần nữa.',
            ]);
        }

        $actor = $request->attributes->get('actor');
        $posterName = $actor->name;
        $postedById = $actor->id;
        $nowStr = now()->format('Y-m-d H:i:s');
        $warehouseName = $request->input('warehouse_name') ?: ($inv->warehouse_name ?: ($inv->deliveryRequest?->warehouse_name ?: 'Kho Chính'));
        $warehouseId = $request->input('warehouse_id') ?: ($inv->warehouse_id ?: $this->resolveWarehouseId($warehouseName));

        $issueResult = $this->issueStockForInvoice($inv, $warehouseId);
        $issueSucceeded = count($issueResult) === $inv->items->count()
            && collect($issueResult)->every(fn (array $result): bool => $result['success'] === true);

        if (! $issueSucceeded) {
            $inv->update([
                'inventory_issue_status' => 'FAILED',
                'inventory_issue_error' => json_encode($issueResult, JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVENTORY_ISSUE_FAILED',
                    'message' => 'Inventory Service chưa xuất kho đầy đủ; chứng từ chưa được ghi sổ.',
                    'details' => $issueResult,
                ],
            ], 502);
        }

        DB::transaction(function () use ($inv, $posterName, $postedById, $nowStr, $warehouseName, $warehouseId) {
            // 1. Cập nhật Invoice
            $inv->update([
                'warehouse_status' => 'DELIVERED',
                'accounting_status' => 'POSTED',
                'warehouse_posted_at' => $nowStr,
                'warehouse_posted_by' => $posterName,
                'posted_at' => $nowStr,
                'posted_by' => $posterName,
                'inventory_issue_status' => 'ISSUED',
                'inventory_issue_error' => null,
                'inventory_issued_at' => now(),
            ]);

            // 1.1 Cập nhật bảng riêng warehouse_dispatches
            WarehouseDispatch::updateOrCreate(
                ['invoice_id' => $inv->id],
                [
                    'invoice_number' => $inv->invoice_number,
                    'delivery_request_id' => $inv->delivery_request_id,
                    'delivery_request_number' => $inv->delivery_request_number,
                    'order_id' => $inv->order_id,
                    'order_number' => $inv->order_number,
                    'customer_name' => $inv->customer_name,
                    'warehouse_name' => $inv->warehouse_name ?: $warehouseName,
                    'warehouse_id' => $warehouseId,
                    'status' => 'DELIVERED',
                    'posted_at' => $nowStr,
                    'posted_by_id' => $postedById,
                    'posted_by_name' => $posterName,
                ]
            );

            // 2. Cập nhật số lượng đã giao thực tế trên từng InvoiceItem & DeliveryRequestItem & SalesOrderItem
            foreach ($inv->items as $invItem) {
                $actualDeliveredQty = (float) $invItem->quantity;
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
                    if (! $drItem && ! empty($invItem->sku)) {
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
                    if (! $soItem && ! empty($invItem->sku)) {
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

                SaleReportService::syncOrderToReport($so);
            }

            // Ghi nhận dòng doanh thu vào revenue_report_items (TK 1312)
            RevenueReportService::recordGoodsIssue($inv, $posterName);
        });

        return response()->json([
            'success' => true,
            'data' => $inv->fresh(['items', 'deliveryRequest.order']),
            'inventory_issue' => $issueResult,
            'message' => 'Đã ghi sổ xuất kho, trừ tồn kho vật lý và cập nhật tiến độ giao hàng thành công.',
        ]);
    }

    public function updateWarehouseStatus(Request $request, $id)
    {
        $inv = Invoice::where('id', $id)->orWhere('invoice_number', $id)->first();
        if (! $inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:SENT_TO_WAREHOUSE,PROCESSING,PROCESSED,READY_TO_SHIP'],
        ]);
        $inv->update(['warehouse_status' => $validated['status']]);

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
        if (! $warehouseName) {
            return 1;
        }
        $nameLower = mb_strtolower($warehouseName);
        if (str_contains($nameLower, 'kĩ thuật') || str_contains($nameLower, 'kỹ thuật') || str_contains($nameLower, 'ki thuat') || str_contains($nameLower, 'ky thuat')) {
            return 2;
        }
        if (str_contains($nameLower, 'chờ xử lí') || str_contains($nameLower, 'chờ xử lý') || str_contains($nameLower, 'cho xu li') || str_contains($nameLower, 'cho xu ly')) {
            return 3;
        }
        if (str_contains($nameLower, 'hồ chí minh') || str_contains($nameLower, 'tp.hcm') || str_contains($nameLower, 'hcm') || str_contains($nameLower, 'nam')) {
            return 2;
        }
        if (str_contains($nameLower, 'đà nẵng') || str_contains($nameLower, 'da nang') || str_contains($nameLower, 'trung')) {
            return 3;
        }

        return 1;
    }

    /**
     * Gửi yêu cầu giữ chỗ (Reserve) tới Inventory Service
     */
    protected function reserveStockForInvoice(Invoice $inv, int $warehouseId): array
    {
        $results = [];
        $items = $inv->items ?: [];
        $inventoryUrl = config('services.inventory.url', 'http://127.0.0.1:8004');
        $timeout = config('services.inventory.timeout', 3);

        foreach ($items as $item) {
            $productId = $item->product_id ?: $item->orderItem?->product_id ?: $item->deliveryRequestItem?->product_id;
            if (! $productId && ! empty($item->sku)) {
                $productId = Product::where('sku', $item->sku)->value('id');
            }

            if (! $productId && ! empty($item->sku)) {
                // Thử tìm trong bảng products
                $prod = DB::table('products')->where('sku', $item->sku)->first();
                $productId = $prod?->id;
            }

            if ($productId && (float) $item->quantity > 0) {
                try {
                    $response = Http::timeout($timeout)->post("{$inventoryUrl}/api/v1/inventory/stocks/reserve", [
                        'order_id' => $inv->invoice_number,
                        'warehouse_id' => $warehouseId,
                        'product_id' => (int) $productId,
                        'quantity' => (float) $item->quantity,
                    ]);

                    $results[] = [
                        'sku' => $item->sku,
                        'product_id' => $productId,
                        'quantity' => (float) $item->quantity,
                        'success' => $response->successful(),
                        'status_code' => $response->status(),
                    ];
                } catch (\Throwable $e) {
                    Log::warning("[InvoiceController] Lỗi gọi Inventory Service Reserve ({$inventoryUrl}): ".$e->getMessage());
                    $results[] = [
                        'sku' => $item->sku,
                        'product_id' => $productId,
                        'quantity' => (float) $item->quantity,
                        'success' => false,
                        'error' => 'Inventory Service khong san sang.',
                    ];
                }
            } else {
                $results[] = [
                    'sku' => $item->sku,
                    'product_id' => $productId,
                    'quantity' => (float) $item->quantity,
                    'success' => false,
                    'error' => 'Không xác định được sản phẩm hoặc số lượng giữ chỗ không hợp lệ.',
                ];
            }
        }

        return $results;
    }

    /**
     * Gửi yêu cầu xuất kho thực tế (Issue) tới Inventory Service
     */
    protected function issueStockForInvoice(Invoice $inv, int $warehouseId): array
    {
        $results = [];
        $items = $inv->items ?: [];
        $orderNumber = $inv->deliveryRequest?->order_number ?: $inv->invoice_number;
        $inventoryUrl = config('services.inventory.url', 'http://127.0.0.1:8004');
        $timeout = config('services.inventory.timeout', 3);

        foreach ($items as $item) {
            $productId = $item->product_id ?: $item->orderItem?->product_id ?: $item->deliveryRequestItem?->product_id;
            if (! $productId && ! empty($item->sku)) {
                $productId = Product::where('sku', $item->sku)->value('id');
            }

            if (! $productId && ! empty($item->sku)) {
                $prod = DB::table('products')->where('sku', $item->sku)->first();
                $productId = $prod?->id;
            }

            if ($productId && (float) $item->quantity > 0) {
                try {
                    $response = Http::timeout($timeout)->post("{$inventoryUrl}/api/v1/inventory/stocks/issue", [
                        'reference_doc' => "{$inv->invoice_number}:{$item->id}",
                        'order_id' => $orderNumber,
                        'warehouse_id' => $warehouseId,
                        'product_id' => (int) $productId,
                        'quantity' => (float) $item->quantity,
                        'notes' => "Xuất kho bán hàng theo chứng từ {$inv->invoice_number}",
                    ]);

                    $results[] = [
                        'sku' => $item->sku,
                        'product_id' => $productId,
                        'quantity' => (float) $item->quantity,
                        'success' => $response->successful(),
                        'status_code' => $response->status(),
                    ];
                } catch (\Throwable $e) {
                    Log::warning("[InvoiceController] Lỗi gọi Inventory Service Issue ({$inventoryUrl}): ".$e->getMessage());
                    $results[] = [
                        'sku' => $item->sku,
                        'product_id' => $productId,
                        'quantity' => (float) $item->quantity,
                        'success' => false,
                        'error' => 'Inventory Service khong san sang.',
                    ];
                }
            } else {
                $results[] = [
                    'sku' => $item->sku,
                    'product_id' => $productId,
                    'quantity' => (float) $item->quantity,
                    'success' => false,
                    'error' => 'Không xác định được sản phẩm hoặc số lượng xuất kho không hợp lệ.',
                ];
            }
        }

        return $results;
    }
}
