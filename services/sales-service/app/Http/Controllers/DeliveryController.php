<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Models\DeliveryRequest;
use App\Models\DeliveryRequestItem;
use App\Models\SalesOrder;
use App\Support\DocumentNumberGenerator;
use App\Support\Outbox;
use App\Support\SalesWorkflow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $deliveries = DeliveryRequest::with(['items.orderItem', 'order'])
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json([
            'success' => true,
            'data' => $deliveries->items(),
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
            ],
        ]);
    }

    public function store(StoreDeliveryRequest $request): JsonResponse
    {
        $order = SalesOrder::with('items')->findOrFail($request->integer('order_id'));
        if (! in_array($order->status, ['APPROVED', 'PROCESSING'], true)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_ORDER_STATUS', 'message' => 'Chỉ có thể tạo yêu cầu giao cho đơn đã được duyệt.'],
            ], 409);
        }

        // Kho xuất phải là kho đang hoạt động và được phép bán (lấy tên chuẩn từ inventory-service)
        $warehouse = $this->sellableWarehouse($request->integer('warehouse_id'), (string) $request->bearerToken());

        $delivery = DB::transaction(function () use ($request, $order, $warehouse): DeliveryRequest {
            $requestNumber = $request->input('request_number') ?: DocumentNumberGenerator::next(
                'delivery-'.now()->format('Y'),
                'DR-'.now()->format('Y').'-',
                6
            );

            $delivery = DeliveryRequest::create([
                'order_id' => $order->id,
                'request_number' => $requestNumber,
                'warehouse_id' => (int) $warehouse['id'],
                'warehouse_name' => (string) ($warehouse['name'] ?? $request->string('warehouse_name')->toString()),
                'shipping_address' => $request->string('shipping_address')->toString(),
                'contact_phone' => $request->input('contact_phone') ?: $order->contact_phone,
                'request_date' => now()->toDateString(),
                'expected_delivery_date' => $request->input('expected_delivery_date'),
                'status' => 'PENDING',
            ]);

            foreach ($request->validated('items') as $itemData) {
                $orderItem = $order->items->firstWhere('id', (int) $itemData['order_item_id']);
                if (! $orderItem) {
                    abort(422, 'Mặt hàng không thuộc đơn bán hàng đã chọn.');
                }

                $alreadyRequested = (float) DB::table('delivery_request_items')
                    ->join('delivery_requests', 'delivery_requests.id', '=', 'delivery_request_items.delivery_request_id')
                    ->where('delivery_request_items.order_item_id', $orderItem->id)
                    ->whereNotIn('delivery_requests.status', ['REJECTED'])
                    ->sum('delivery_request_items.quantity');
                $remaining = max(0, (float) $orderItem->quantity - $alreadyRequested);
                if ((float) $itemData['quantity'] > $remaining) {
                    abort(422, "Số lượng yêu cầu của SKU {$orderItem->sku} vượt quá số lượng còn lại {$remaining}.");
                }

                DeliveryRequestItem::create([
                    'delivery_request_id' => $delivery->id,
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'sku' => $orderItem->sku,
                    'quantity' => (float) $itemData['quantity'],
                ]);
            }

            $this->recordEvent('DeliveryRequested', 'sales.delivery.requested', $delivery, $order, $request);

            return $delivery;
        });

        return response()->json(['success' => true, 'data' => $delivery->load(['items.orderItem'])], 201);
    }

    public function updateStatus(UpdateStatusRequest $request, int|string $id): JsonResponse
    {
        $dr = DeliveryRequest::with('items.orderItem')->where('id', $id)->orWhere('request_number', $id)->first();
        if (! $dr) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $nextStatus = $request->string('status')->upper()->toString();
        if (! SalesWorkflow::canTransition('delivery', $dr->status, $nextStatus)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATUS_TRANSITION',
                    'message' => "Không thể chuyển trạng thái từ {$dr->status} sang {$nextStatus}.",
                ],
            ], 409);
        }

        DB::transaction(function () use ($dr, $nextStatus, $request): void {
            $dr->update(['status' => $nextStatus]);
            // Chỉ kết quả duyệt mới phát event thông báo; các bước giao hàng sau đó không cần
            if (in_array($nextStatus, ['APPROVED', 'REJECTED'], true)) {
                $eventType = $nextStatus === 'APPROVED' ? 'DeliveryApproved' : 'DeliveryRejected';
                $this->recordEvent($eventType, 'sales.delivery.'.strtolower($nextStatus), $dr, $dr->order, $request);
            }
        });

        return response()->json(['success' => true, 'data' => $dr]);
    }

    /** @return array<string, mixed> */
    private function sellableWarehouse(int $warehouseId, string $token): array
    {
        try {
            $response = Http::withToken($token)->acceptJson()
                ->timeout(config('services.inventory.timeout', 3))
                ->get(config('services.inventory.url').'/api/v1/inventory/warehouses');
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['warehouse_id' => 'Không kết nối được Inventory Service để kiểm tra kho xuất.']);
        }
        if (! $response->successful()) {
            throw ValidationException::withMessages(['warehouse_id' => 'Không đọc được danh mục kho từ Inventory Service.']);
        }

        $warehouse = collect($response->json('data', []))->firstWhere('id', $warehouseId);
        if (! $warehouse || ! ($warehouse['is_active'] ?? false) || ! ($warehouse['can_sell'] ?? false)) {
            throw ValidationException::withMessages(['warehouse_id' => 'Kho xuất phải là kho đang hoạt động và được phép bán.']);
        }

        return $warehouse;
    }

    private function recordEvent(string $eventType, string $routingKey, DeliveryRequest $delivery, ?SalesOrder $order, Request $request): void
    {
        Outbox::record($eventType, $routingKey, 'DeliveryRequest', $delivery->id, [
            'delivery_request_id' => $delivery->id,
            'request_number' => $delivery->request_number,
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'customer_name' => $order?->customer_name,
            'warehouse_id' => $delivery->warehouse_id ? (int) $delivery->warehouse_id : null,
            'warehouse_name' => $delivery->warehouse_name,
            'expected_delivery_date' => $delivery->expected_delivery_date,
            'created_by_id' => is_numeric($order?->created_by) ? (int) $order->created_by : null,
            'actor' => Outbox::actor($request->attributes->get('actor')),
        ]);
    }
}
