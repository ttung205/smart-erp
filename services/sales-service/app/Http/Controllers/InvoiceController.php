<?php

namespace App\Http\Controllers;

use App\Exceptions\StockReservationFailedException;
use App\Http\Requests\StoreInvoiceRequest;
use App\Infrastructure\Clients\CatalogClient;
use App\Infrastructure\Clients\DependencyUnavailableException;
use App\Infrastructure\Clients\IdentityClient;
use App\Models\DeliveryRequest;
use App\Models\DeliveryRequestItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SalesAttachment;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\WarehouseDispatch;
use App\Services\RevenueReportService;
use App\Services\SaleReportService;
use App\Support\DocumentNumberGenerator;
use App\Support\Outbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly CatalogClient $catalog,
        private readonly IdentityClient $identity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::with([
            'items.orderItem',
            'deliveryRequest.order',
            'deliveryRequest.items',
            'warehouseDispatch',
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
        $warehouseId = $dr?->warehouse_id ?: $this->resolveWarehouseId($warehouseName);

        $reservation = null;
        $reservedNumber = null;
        try {
            $inv = DB::transaction(function () use ($request, $drId, $drNumber, $dr, $order, $currency, $exchangeRate, $createdBy, $sendImmediately, $warehouseId, &$reservation, &$reservedNumber) {
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

                    if (! $productId) {
                        $productId = $this->productIdForSku($sku);
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

                // Nghiệp vụ: giữ hàng ngay khi lập chứng từ (architecture.md §8).
                // Không giữ được (thiếu hàng, Inventory lỗi) → rollback, chứng từ không được tạo.
                $reservation = $this->reserveStockForInvoice($inv, $warehouseId, $request->bearerToken());
                if (! $reservation['reserved']) {
                    throw new StockReservationFailedException($reservation['error']);
                }
                $reservedNumber = $inv->invoice_number;
                $this->applyReservationResult($inv, $warehouseId, $reservation);

                return $inv;
            });
        } catch (StockReservationFailedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Đã giữ hàng nhưng lưu chứng từ thất bại → nhả lại để hàng không bị treo
            if ($reservedNumber) {
                $this->releaseStockForInvoice($reservedNumber, $request->bearerToken());
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'data' => $inv->load('items', 'deliveryRequest.order', 'attachments'),
            'reservation' => $reservation,
            'message' => "Đã tạo chứng từ {$inv->invoice_number} và giữ hàng tại kho thành công.",
        ], 201);
    }

    public function show($id)
    {
        $inv = Invoice::with([
            'items.orderItem',
            'deliveryRequest.order',
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
        if ($inv->warehouse_status === 'CANCELLED') {
            return $this->cancelledResponse($inv);
        }

        if ($inv->inventory_reservation_status === 'RESERVED') {
            return response()->json([
                'success' => true,
                'data' => $inv,
                'reservation' => [],
                'message' => 'Chứng từ đã được giữ chỗ tồn kho trước đó.',
            ]);
        }

        $warehouseName = $request->input('warehouse_name') ?: ($inv->deliveryRequest?->warehouse_name ?: 'Kho Tổng Miền Bắc (Hà Nội)');
        $warehouseId = $inv->inventory_warehouse_id ?: ($inv->deliveryRequest?->warehouse_id ?: $this->resolveWarehouseId($warehouseName));

        $reservation = $this->reserveStockForInvoice($inv, $warehouseId, $request->bearerToken());
        $this->applyReservationResult($inv, $warehouseId, $reservation);
        $succeeded = $reservation['reserved'];

        return response()->json([
            'success' => $succeeded,
            'data' => $inv,
            'reservation' => $reservation,
            'error' => $reservation['error'],
            'message' => $succeeded
                ? "Đã đồng bộ giữ chỗ tồn kho cho chứng từ {$inv->invoice_number} thành công."
                : ($reservation['error']['message'] ?? 'Không thể giữ chỗ tại Inventory Service.'),
        ], $succeeded ? 200 : StockReservationFailedException::status($reservation['error']['code'] ?? null));
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
        if ($inv->warehouse_status === 'CANCELLED') {
            return $this->cancelledResponse($inv);
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

        DB::transaction(function () use ($inv, $request): void {
            $inv->update([
                'warehouse_status' => 'SENT_TO_WAREHOUSE',
            ]);

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
                    'status' => 'SENT_TO_WAREHOUSE',
                ]
            );

            Outbox::record('WarehouseDispatchRequested', 'sales.dispatch.requested', 'WarehouseDispatch', $dispatch->id, [
                'dispatch_id' => $dispatch->id,
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'order_number' => $inv->order_number,
                'delivery_request_number' => $inv->delivery_request_number,
                'customer_name' => $inv->customer_name,
                'warehouse_id' => $inv->inventory_warehouse_id ? (int) $inv->inventory_warehouse_id : null,
                'warehouse_name' => $inv->warehouse_name,
                'actor' => Outbox::actor($request->attributes->get('actor')),
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $inv->fresh(['warehouseDispatch']),
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
        if ($inv->warehouse_status === 'CANCELLED') {
            return $this->cancelledResponse($inv);
        }

        $pickerId = $request->input('picker_id') ?: null;
        $pickerName = $request->input('picker_name') ?: null;
        if ($pickerId && ! $pickerName) {
            $pickerName = $this->identity->name((int) $pickerId);
        }

        $packerId = $request->input('packer_id') ?: null;
        $packerName = $request->input('packer_name') ?: null;
        if ($packerId && ! $packerName) {
            $packerName = $this->identity->name((int) $packerId);
        }

        $shippingMethod = $request->input('shipping_method', 'CUSTOMER_PICKUP');
        $shipperId = $request->input('shipper_id') ?: null;
        $shipperName = $request->input('shipper_name') ?: null;
        if ($shippingMethod === 'WAREHOUSE_DELIVERY' && $shipperId && ! $shipperName) {
            $shipperName = $this->identity->name((int) $shipperId);
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
            'data' => $inv->fresh(['warehouseDispatch']),
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
        if ($inv->warehouse_status === 'CANCELLED') {
            return $this->cancelledResponse($inv);
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
        if ($inv->inventory_reservation_status !== 'RESERVED') {
            // Chưa giữ chỗ mà xuất kho thì tồn thực tế bị trừ nhưng tồn khả dụng không trừ → lệch số liệu kho
            return response()->json([
                'success' => false,
                'error' => ['code' => 'STOCK_NOT_RESERVED', 'message' => 'Chứng từ chưa giữ chỗ tồn kho thành công nên chưa thể xuất kho.'],
            ], 409);
        }

        // Xuất kho bắt buộc ở đúng kho đã giữ chỗ, nếu không tồn thực tế bị trừ ở kho này
        // trong khi tồn khả dụng đã trừ ở kho khác
        $warehouseName = $inv->warehouse_name ?: 'Kho Chính';
        $warehouseId = (int) ($inv->inventory_warehouse_id ?: ($inv->deliveryRequest?->warehouse_id ?: $this->resolveWarehouseId($warehouseName)));
        if ($request->filled('warehouse_id') && (int) $request->input('warehouse_id') !== $warehouseId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'WAREHOUSE_MISMATCH', 'message' => 'Chỉ được xuất kho tại đúng kho đã giữ chỗ cho chứng từ này.'],
            ], 422);
        }

        $issueResult = $this->issueStockForInvoice($inv, $warehouseId, $request->bearerToken());
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

    /**
     * Hủy chứng từ bán hàng chưa xuất kho:
     * - nhả toàn bộ hàng đã giữ ở Inventory (làm trước; Inventory lỗi thì chưa đổi gì, hủy lại được),
     * - phần còn phải giao của yêu cầu giao về 0 và đóng yêu cầu → số lượng chưa giao của đơn bán tăng lại,
     *   có thể lập yêu cầu giao mới.
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $inv = Invoice::with(['deliveryRequest.items'])->where('id', $id)->orWhere('invoice_number', $id)->first();
        if (! $inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }
        if ($inv->warehouse_status === 'CANCELLED') {
            return response()->json(['success' => true, 'data' => $inv, 'message' => "Chứng từ {$inv->invoice_number} đã được hủy trước đó."]);
        }
        if ($inv->inventory_issue_status === 'ISSUED' || $inv->accounting_status === 'POSTED' || $inv->warehouse_status === 'DELIVERED') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVOICE_ALREADY_ISSUED', 'message' => 'Chứng từ đã xuất kho, không thể hủy. Hãy lập yêu cầu trả hàng.'],
            ], 409);
        }

        $release = $this->releaseStockForInvoice($inv->invoice_number, $request->bearerToken());
        if (! $release['released']) {
            throw new StockReservationFailedException($release['error']);
        }

        $actor = $request->attributes->get('actor');
        DB::transaction(function () use ($inv, $actor, $validated): void {
            $inv->update([
                'warehouse_status' => 'CANCELLED',
                'inventory_reservation_status' => 'RELEASED',
                'cancelled_at' => now(),
                'cancelled_by_id' => $actor->id,
                'cancelled_by_name' => $actor->name,
                'cancel_reason' => $validated['reason'],
            ]);
            WarehouseDispatch::where('invoice_id', $inv->id)->update(['status' => 'CANCELLED']);

            $dr = $inv->deliveryRequest;
            if ($dr) {
                foreach ($dr->items as $drItem) {
                    // Phần chưa giao về 0: trả lại cho "số lượng chưa giao" của dòng đơn bán
                    $drItem->update(['quantity' => (float) $drItem->delivered_quantity]);
                }
                $dr->update(['status' => $dr->items->sum('delivered_quantity') > 0 ? 'DELIVERED' : 'CANCELLED']);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $inv->fresh(['items', 'deliveryRequest.items']),
            'message' => "Đã hủy chứng từ {$inv->invoice_number} và nhả hàng đã giữ tại kho.",
        ]);
    }

    public function updateWarehouseStatus(Request $request, $id)
    {
        $inv = Invoice::where('id', $id)->orWhere('invoice_number', $id)->first();
        if (! $inv) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }
        if ($inv->warehouse_status === 'CANCELLED') {
            return $this->cancelledResponse($inv);
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
     * Giữ chỗ cả chứng từ bằng MỘT lần gọi Inventory (POST /stocks/reserve/batch): đủ hàng cho mọi dòng
     * mới giữ, thiếu một dòng thì Inventory rollback, không giữ dòng nào. Gọi lại an toàn: Inventory bỏ qua
     * dòng đã giữ theo mã chứng từ (kể cả chứng từ PARTIAL từ cách giữ từng dòng trước đây).
     *
     * @return array{reserved: bool, items: list<array<string, mixed>>, error: array<string, mixed>|null}
     */
    protected function reserveStockForInvoice(Invoice $inv, int $warehouseId, ?string $token = null): array
    {
        $items = [];
        $unresolved = [];
        foreach ($inv->items as $item) {
            $productId = $item->product_id ?: $item->orderItem?->product_id ?: $item->deliveryRequestItem?->product_id
                ?: $this->productIdForSku($item->sku);
            $quantity = (int) round((float) $item->quantity);
            if (! $productId || $quantity <= 0) {
                $unresolved[] = $item->sku ?: "#{$item->id}";

                continue;
            }
            $items[] = ['sku' => $item->sku, 'warehouse_id' => $warehouseId, 'product_id' => (int) $productId, 'quantity' => $quantity];
        }

        if ($unresolved !== []) {
            return ['reserved' => false, 'items' => $items, 'error' => [
                'code' => 'PRODUCT_UNRESOLVED',
                'message' => 'Không xác định được sản phẩm hoặc số lượng của: '.implode(', ', $unresolved),
            ]];
        }

        $inventoryUrl = config('services.inventory.url', 'http://127.0.0.1:8004');
        try {
            // Inventory Service yêu cầu JWT: chuyển tiếp token của người dùng đang thao tác
            $response = Http::withToken((string) $token)->acceptJson()
                ->timeout(config('services.inventory.timeout', 3))
                ->post("{$inventoryUrl}/api/v1/inventory/stocks/reserve/batch", [
                    'order_id' => $inv->invoice_number,
                    'items' => array_map(fn (array $i): array => array_diff_key($i, ['sku' => true]), $items),
                ]);
        } catch (\Throwable $e) {
            Log::warning("[InvoiceController] Lỗi gọi Inventory Service reserve/batch ({$inventoryUrl}): ".$e->getMessage());

            return ['reserved' => false, 'items' => $items, 'error' => [
                'code' => 'DEPENDENCY_UNAVAILABLE',
                'message' => 'Inventory Service không phản hồi, vui lòng đồng bộ giữ chỗ lại sau.',
            ]];
        }

        if ($response->successful()) {
            return ['reserved' => true, 'items' => $items, 'error' => null];
        }

        $error = $response->json('error') ?? ['code' => 'INVENTORY_ERROR', 'message' => "Inventory Service trả về HTTP {$response->status()}."];
        if (($error['code'] ?? null) === 'INSUFFICIENT_STOCK' && isset($error['details']['product_id'])) {
            $sku = collect($items)->firstWhere('product_id', (int) $error['details']['product_id'])['sku'] ?? "#{$error['details']['product_id']}";
            $error['message'] = "Không đủ hàng để lập chứng từ: SKU {$sku} cần {$error['details']['requested']}, kho chỉ còn {$error['details']['available']} khả dụng.";
        }

        return ['reserved' => false, 'items' => $items, 'error' => $error];
    }

    /**
     * Nhả toàn bộ hàng đã giữ cho chứng từ (POST /stocks/release/document). Idempotent phía Inventory.
     *
     * @return array{released: bool, error: array<string, mixed>|null}
     */
    protected function releaseStockForInvoice(string $invoiceNumber, ?string $token = null): array
    {
        $inventoryUrl = config('services.inventory.url', 'http://127.0.0.1:8004');
        try {
            $response = Http::withToken((string) $token)->acceptJson()
                ->timeout(config('services.inventory.timeout', 3))
                ->post("{$inventoryUrl}/api/v1/inventory/stocks/release/document", ['order_id' => $invoiceNumber]);
        } catch (\Throwable $e) {
            Log::warning("[InvoiceController] Lỗi gọi Inventory Service release/document ({$inventoryUrl}): ".$e->getMessage());

            return ['released' => false, 'error' => [
                'code' => 'DEPENDENCY_UNAVAILABLE',
                'message' => 'Inventory Service không phản hồi, chưa nhả được hàng. Vui lòng thử hủy lại sau.',
            ]];
        }

        return $response->successful()
            ? ['released' => true, 'error' => null]
            : ['released' => false, 'error' => $response->json('error') ?? ['code' => 'INVENTORY_ERROR', 'message' => "Inventory Service trả về HTTP {$response->status()}."]];
    }

    private function cancelledResponse(Invoice $inv): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'INVOICE_CANCELLED', 'message' => "Chứng từ {$inv->invoice_number} đã bị hủy."],
        ], 409);
    }

    /** @param  array{reserved: bool, error: array<string, mixed>|null}  $reservation */
    private function applyReservationResult(Invoice $inv, int $warehouseId, array $reservation): void
    {
        $inv->update([
            'inventory_warehouse_id' => $warehouseId,
            'inventory_reservation_status' => $reservation['reserved'] ? 'RESERVED' : 'FAILED',
            'inventory_reservation_error' => $reservation['error'] ? json_encode($reservation['error'], JSON_UNESCAPED_UNICODE) : null,
            'inventory_reserved_at' => $reservation['reserved'] ? now() : null,
        ]);
    }

    /**
     * Gửi yêu cầu xuất kho thực tế (Issue) tới Inventory Service
     */
    protected function issueStockForInvoice(Invoice $inv, int $warehouseId, ?string $token = null): array
    {
        $results = [];
        $items = $inv->items ?: [];
        $orderNumber = $inv->deliveryRequest?->order_number ?: $inv->invoice_number;
        $inventoryUrl = config('services.inventory.url', 'http://127.0.0.1:8004');
        $timeout = config('services.inventory.timeout', 3);

        foreach ($items as $item) {
            $productId = $item->product_id ?: $item->orderItem?->product_id ?: $item->deliveryRequestItem?->product_id;
            if (! $productId) {
                $productId = $this->productIdForSku($item->sku);
            }

            if ($productId && (float) $item->quantity > 0) {
                try {
                    $response = Http::withToken((string) $token)->acceptJson()->timeout($timeout)->post("{$inventoryUrl}/api/v1/inventory/stocks/issue", [
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

    /** Dòng cũ chưa lưu product_id: tra catalog theo SKU; catalog không phản hồi thì coi như chưa xác định được. */
    private function productIdForSku(?string $sku): ?int
    {
        if (empty($sku)) {
            return null;
        }

        try {
            return $this->catalog->productsBySkus([$sku])[mb_strtoupper($sku)]['id'] ?? null;
        } catch (DependencyUnavailableException) {
            return null;
        }
    }
}
