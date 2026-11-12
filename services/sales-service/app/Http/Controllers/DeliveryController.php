<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Infrastructure\Clients\InternalHttp;
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
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class DeliveryController extends Controller
{
    #[OA\Get(
        path: '/deliveries',
        summary: 'Danh sách yêu cầu giao hàng',
        description: 'Yêu cầu quyền delivery.view.',
        tags: ['Yêu cầu giao hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Yêu cầu giao kèm dòng hàng và đơn bán'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền delivery.view'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = DeliveryRequest::with(['items.orderItem', 'order'])->orderByDesc('id');
        // Lọc theo đơn bán: màn hình lập yêu cầu giao cần mọi yêu cầu cũ của đúng đơn đó, không chỉ trang mới nhất
        if ($request->filled('order_id')) {
            $query->where('order_id', $request->integer('order_id'));
        }
        // Tìm ở server trên toàn bộ yêu cầu giao: mã yêu cầu, địa chỉ, và số đơn / khách hàng của đơn bán (cùng DB sales)
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '\%_').'%';
            $query->where(fn ($w) => $w->where('request_number', 'like', $like)
                ->orWhere('shipping_address', 'like', $like)
                ->when(ctype_digit($search), fn ($q) => $q->orWhere('id', (int) $search))
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', $like)
                    ->orWhere('customer_order_number', 'like', $like)
                    ->orWhere('customer_code', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)));
        }
        $deliveries = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));

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

    #[OA\Post(
        path: '/deliveries',
        summary: 'Tạo yêu cầu giao hàng',
        description: 'Yêu cầu quyền delivery.create. Chỉ cho đơn bán đã duyệt; chọn kho bán và số lượng từng dòng không vượt số còn phải giao.',
        tags: ['Yêu cầu giao hàng'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['order_id', 'warehouse_id', 'warehouse_name', 'shipping_address', 'items'],
                properties: [
                    new OA\Property(property: 'order_id', type: 'integer'),
                    new OA\Property(property: 'request_number', type: 'string', nullable: true, maxLength: 50, description: 'Bỏ trống thì hệ thống tự sinh'),
                    new OA\Property(property: 'warehouse_id', type: 'integer', minimum: 1),
                    new OA\Property(property: 'warehouse_name', type: 'string', maxLength: 150),
                    new OA\Property(property: 'shipping_address', type: 'string', maxLength: 2000),
                    new OA\Property(property: 'contact_phone', type: 'string', nullable: true, maxLength: 50),
                    new OA\Property(property: 'expected_delivery_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        minItems: 1,
                        items: new OA\Items(
                            required: ['order_item_id', 'sku', 'quantity'],
                            properties: [
                                new OA\Property(property: 'order_item_id', type: 'integer'),
                                new OA\Property(property: 'product_id', type: 'integer', nullable: true),
                                new OA\Property(property: 'sku', type: 'string', maxLength: 100),
                                new OA\Property(property: 'quantity', type: 'number', exclusiveMinimum: 0),
                            ],
                            type: 'object'
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Yêu cầu giao mới kèm dòng hàng'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền delivery.create'),
            new OA\Response(response: 409, description: 'INVALID_ORDER_STATUS: đơn bán chưa được duyệt'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ, mặt hàng không thuộc đơn hoặc vượt số lượng còn lại'),
        ]
    )]
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
                    abort(422, "Số lượng yêu cầu của Internal Code {$orderItem->sku} vượt quá số lượng còn lại {$remaining}.");
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

    #[OA\Put(
        path: '/deliveries/{id}/status',
        summary: 'Cập nhật trạng thái yêu cầu giao hàng',
        description: 'Yêu cầu quyền delivery.manage. Chỉ cho phép chuyển trạng thái theo luồng giao hàng.',
        tags: ['Yêu cầu giao hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Id hoặc số yêu cầu giao', schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['status'], properties: [new OA\Property(property: 'status', type: 'string', maxLength: 50)])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Yêu cầu giao sau cập nhật'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền delivery.manage'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 409, description: 'INVALID_STATUS_TRANSITION: không được chuyển sang trạng thái này'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
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
            $response = InternalHttp::inventory($token)
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
