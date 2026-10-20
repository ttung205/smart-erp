<?php

namespace App\Http\Controllers;

use App\Http\Requests\InspectSaleReturnRequest;
use App\Http\Requests\StoreSaleReturnRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SaleReturnRequest;
use App\Models\SaleReturnRequestItem;
use App\Models\SalesAttachment;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SaleReturnRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SaleReturnRequest::with(['documents', 'items', 'saleReturn'])->latest('id');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn ($builder) => $builder->where('request_number', 'like', $search)
                ->orWhere('customer_name', 'like', $search));
        }
        $paginator = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json(['success' => true, 'data' => $paginator->items(), 'meta' => [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
        ]]);
    }

    public function eligibleInvoices(Request $request): JsonResponse
    {
        $query = Invoice::with(['items.orderItem', 'deliveryRequest.order'])
            ->where('warehouse_status', 'DELIVERED')
            ->where('accounting_status', 'POSTED')
            ->latest('id');
        if ($request->filled('customer_id')) {
            $customerId = $request->integer('customer_id');
            $query->whereHas('deliveryRequest.order', fn ($builder) => $builder->where('customer_id', $customerId));
        }

        $invoices = $query->limit(200)->get()->map(function (Invoice $invoice): array {
            $items = $invoice->items->map(function (InvoiceItem $item): array {
                $allocated = $this->allocatedQuantity($item->id);
                $delivered = (float) ($item->delivered_quantity ?: $item->quantity);

                return [
                    'id' => $item->id, 'invoice_id' => $item->invoice_id, 'product_id' => $item->product_id,
                    'sku' => $item->sku, 'product_name' => $item->product_name, 'brand' => $item->brand,
                    'unit' => $item->unit, 'delivered_quantity' => $delivered,
                    'previously_returned_quantity' => $allocated,
                    'available_return_quantity' => max(0, $delivered - $allocated),
                    'unit_price' => (float) $item->unit_price,
                ];
            })->filter(fn (array $item): bool => $item['available_return_quantity'] > 0)->values();

            return [
                'id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date, 'customer_id' => $invoice->customer_id,
                'customer_code' => $invoice->deliveryRequest?->order?->customer_code,
                'customer_name' => $invoice->customer_name, 'items' => $items,
            ];
        })->filter(fn (array $invoice): bool => $invoice['items']->isNotEmpty())->values();

        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function store(StoreSaleReturnRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $actor = $request->attributes->get('actor');
        $returnRequest = DB::transaction(function () use ($validated, $actor): SaleReturnRequest {
            $invoices = Invoice::with(['deliveryRequest.order', 'items.orderItem'])
                ->whereIn('id', $validated['invoice_ids'])->lockForUpdate()->get();
            if ($invoices->count() !== count($validated['invoice_ids'])) {
                throw ValidationException::withMessages(['invoice_ids' => 'Có chứng từ không tồn tại.']);
            }
            if ($invoices->contains(fn (Invoice $invoice): bool => $invoice->warehouse_status !== 'DELIVERED' || $invoice->accounting_status !== 'POSTED')) {
                throw ValidationException::withMessages(['invoice_ids' => 'Chỉ được chọn chứng từ đã giao và đã ghi sổ.']);
            }
            $customerIds = $invoices->map(fn (Invoice $invoice) => $invoice->customer_id)->unique();
            if ($customerIds->count() !== 1 || ($validated['customer_id'] ?? null) && $customerIds->first() !== (int) $validated['customer_id']) {
                throw ValidationException::withMessages(['invoice_ids' => 'Các chứng từ phải thuộc cùng một khách hàng.']);
            }
            // Khách hàng lấy từ snapshot trên đơn bán hàng của chứng từ
            $order = $invoices->first()->deliveryRequest?->order;
            if (! $order) {
                throw ValidationException::withMessages(['customer_id' => 'Không tìm thấy khách hàng của chứng từ.']);
            }
            $invoiceItems = InvoiceItem::with(['orderItem'])
                ->whereIn('invoice_id', $invoices->pluck('id'))->whereIn('id', collect($validated['items'])->pluck('invoice_item_id'))
                ->lockForUpdate()->get()->keyBy('id');
            if ($invoiceItems->count() !== count($validated['items'])) {
                throw ValidationException::withMessages(['items' => 'Có mặt hàng không thuộc các chứng từ đã chọn.']);
            }

            $requestNumber = $this->nextRequestNumber((string) $order->customer_code);
            $returnRequest = SaleReturnRequest::create([
                'request_number' => $requestNumber, 'customer_id' => $order->customer_id,
                'customer_code' => $order->customer_code, 'customer_name' => $order->customer_name,
                'reason' => $validated['reason'], 'notes' => $validated['notes'] ?? null,
                'status' => SaleReturnRequest::PENDING_INSPECTION,
                'created_by_id' => $actor->id, 'created_by_name' => $actor->name, 'total_amount' => 0,
            ]);
            foreach ($invoices as $invoice) {
                $returnRequest->documents()->create(['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number]);
            }
            $total = 0;
            foreach ($validated['items'] as $payload) {
                $item = $invoiceItems->get($payload['invoice_item_id']);
                $delivered = (float) ($item->delivered_quantity ?: $item->quantity);
                $allocated = $this->allocatedQuantity($item->id);
                $quantity = (float) $payload['requested_quantity'];
                if ($quantity > $delivered - $allocated) {
                    throw ValidationException::withMessages(['items' => "Số lượng trả của {$item->sku} vượt quá số lượng còn có thể trả."]);
                }
                $lineTotal = $quantity * (float) $item->unit_price;
                $total += $lineTotal;
                $returnRequest->items()->create([
                    'invoice_id' => $item->invoice_id, 'invoice_item_id' => $item->id,
                    'product_id' => $item->product_id, 'invoice_number' => $invoices->firstWhere('id', $item->invoice_id)->invoice_number,
                    'sku' => $item->sku, 'product_name' => $item->product_name,
                    'brand' => $item->brand, 'unit' => $item->unit,
                    'delivered_quantity' => $delivered, 'previously_returned_quantity' => $allocated,
                    'requested_quantity' => $quantity, 'unit_price' => $item->unit_price, 'line_total' => $lineTotal,
                ]);
            }
            $returnRequest->update(['total_amount' => $total]);
            SalesAttachment::whereIn('id', $validated['attachment_ids'] ?? [])->whereNull('attachable_id')
                ->where('uploaded_by', $actor->name)->update(['attachable_id' => $returnRequest->id, 'attachable_type' => 'sale_return_request']);

            return $returnRequest;
        });

        return response()->json(['success' => true, 'data' => $this->loadRequest($returnRequest), 'message' => 'Đã tạo yêu cầu nhập trả.'], 201);
    }

    public function show(SaleReturnRequest $saleReturnRequest): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->loadRequest($saleReturnRequest)]);
    }

    public function startInspection(Request $request, SaleReturnRequest $saleReturnRequest): JsonResponse
    {
        $this->requireStatus($saleReturnRequest, [SaleReturnRequest::PENDING_INSPECTION]);
        $actor = $request->attributes->get('actor');
        $saleReturnRequest->update(['status' => SaleReturnRequest::INSPECTING, 'inspection_started_by_id' => $actor->id,
            'inspection_started_by_name' => $actor->name, 'inspection_started_at' => now()]);

        return $this->success($saleReturnRequest, 'Đã bắt đầu kiểm tra hàng trả.');
    }

    public function inspect(InspectSaleReturnRequest $request, SaleReturnRequest $saleReturnRequest): JsonResponse
    {
        $this->requireStatus($saleReturnRequest, [SaleReturnRequest::INSPECTING]);
        $items = $saleReturnRequest->items->keyBy('id');
        if ($items->count() !== count($request->validated('items'))) {
            throw ValidationException::withMessages(['items' => 'Phải đánh giá toàn bộ mặt hàng trong yêu cầu.']);
        }
        DB::transaction(function () use ($request, $saleReturnRequest, $items): void {
            foreach ($request->validated('items') as $payload) {
                $item = $items->get($payload['id']);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Mặt hàng đánh giá không thuộc yêu cầu.']);
                }
                $accepted = $payload['quality_status'] === 'ELIGIBLE' ? (float) $item->requested_quantity : 0;
                $item->update(['quality_status' => $payload['quality_status'], 'accepted_quantity' => $accepted,
                    'rejected_quantity' => $item->requested_quantity - $accepted, 'assessment_note' => null]);
            }
            $actor = $request->attributes->get('actor');
            $saleReturnRequest->update(['inspected_by_id' => $actor->id, 'inspected_by_name' => $actor->name, 'inspected_at' => now()]);
        });

        return $this->success($saleReturnRequest, 'Đã lưu kết quả đánh giá chất lượng.');
    }

    public function reject(Request $request, SaleReturnRequest $saleReturnRequest): JsonResponse
    {
        $this->requireStatus($saleReturnRequest, [SaleReturnRequest::PENDING_INSPECTION, SaleReturnRequest::INSPECTING]);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $saleReturnRequest->update(['status' => SaleReturnRequest::REJECTED, 'rejection_reason' => $data['reason']]);

        return $this->success($saleReturnRequest, 'Đã từ chối yêu cầu nhập trả.');
    }

    public function receiveTechnical(Request $request, SaleReturnRequest $saleReturnRequest): JsonResponse
    {
        $this->requireStatus($saleReturnRequest, [SaleReturnRequest::INSPECTING]);
        $data = $request->validate(['warehouse_id' => ['required', 'integer'], 'warehouse_name' => ['required', 'string', 'max:255']]);
        $warehouse = $this->ensureWarehouseType((int) $data['warehouse_id'], false, $request->bearerToken());
        $data['warehouse_name'] = $warehouse['name'];
        if (! $saleReturnRequest->items()->where('quality_status', '!=', 'PENDING')->exists() || $saleReturnRequest->items()->where('quality_status', 'PENDING')->exists()) {
            throw ValidationException::withMessages(['items' => 'Phải đánh giá toàn bộ mặt hàng trước khi nhập kho kỹ thuật.']);
        }
        $eligible = $saleReturnRequest->items()->where('accepted_quantity', '>', 0)->get();
        if ($eligible->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Không có mặt hàng đủ điều kiện nhập kho kỹ thuật.']);
        }
        $reference = "{$saleReturnRequest->request_number}-TECH";
        $response = Http::withToken((string) $request->bearerToken())
            ->timeout(config('services.inventory.timeout', 3))
            ->post(config('services.inventory.url').'/api/v1/inventory/sales-returns/receive', [
                'reference_doc' => $reference,
                'warehouse_id' => $data['warehouse_id'],
                'items' => $eligible->groupBy('product_id')->map(fn ($items, $productId): array => [
                    'product_id' => (int) $productId,
                    'quantity' => (int) $items->sum('accepted_quantity'),
                ])->values()->all(),
            ]);
        $this->ensureInventorySuccess($response);
        foreach ($eligible as $item) {
            $item->update(['technical_receive_reference' => $reference]);
        }
        $actor = $request->attributes->get('actor');
        $saleReturnRequest->update(['status' => SaleReturnRequest::TECHNICAL_RECEIVED,
            'technical_warehouse_id' => $data['warehouse_id'], 'technical_warehouse_name' => $data['warehouse_name'],
            'technical_received_by_id' => $actor->id, 'technical_received_by_name' => $actor->name, 'technical_received_at' => now()]);

        return $this->success($saleReturnRequest, 'Đã nhập hàng đạt chất lượng vào kho kỹ thuật.');
    }

    public function requestMainWarehouse(Request $request, SaleReturnRequest $saleReturnRequest): JsonResponse
    {
        $this->requireStatus($saleReturnRequest, [SaleReturnRequest::TECHNICAL_RECEIVED, SaleReturnRequest::MAIN_REJECTED]);
        $data = $request->validate(['warehouse_id' => ['required', 'integer'], 'warehouse_name' => ['required', 'string', 'max:255']]);
        $warehouse = $this->ensureWarehouseType((int) $data['warehouse_id'], true, $request->bearerToken());
        $data['warehouse_name'] = $warehouse['name'];
        if ((int) $data['warehouse_id'] === (int) $saleReturnRequest->technical_warehouse_id) {
            throw ValidationException::withMessages(['warehouse_id' => 'Kho bán phải khác kho kỹ thuật.']);
        }
        $actor = $request->attributes->get('actor');
        $saleReturnRequest->update(['status' => SaleReturnRequest::PENDING_MAIN_APPROVAL,
            'sales_warehouse_id' => $data['warehouse_id'], 'sales_warehouse_name' => $data['warehouse_name'],
            'main_requested_by_id' => $actor->id, 'main_requested_by_name' => $actor->name, 'main_requested_at' => now(),
            'main_rejection_reason' => null]);

        return $this->success($saleReturnRequest, 'Đã gửi yêu cầu nhập kho chính.');
    }

    public function approveMain(Request $request, SaleReturnRequest $saleReturnRequest): JsonResponse
    {
        $this->requireStatus($saleReturnRequest, [SaleReturnRequest::PENDING_MAIN_APPROVAL]);
        $actor = $request->attributes->get('actor');
        $saleReturnRequest->update(['status' => SaleReturnRequest::PENDING_POSTING,
            'main_approved_by_id' => $actor->id, 'main_approved_by_name' => $actor->name, 'main_approved_at' => now()]);

        return $this->success($saleReturnRequest, 'Đã duyệt nhập kho chính, chờ ghi sổ.');
    }

    public function rejectMain(Request $request, SaleReturnRequest $saleReturnRequest): JsonResponse
    {
        $this->requireStatus($saleReturnRequest, [SaleReturnRequest::PENDING_MAIN_APPROVAL]);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $actor = $request->attributes->get('actor');
        $saleReturnRequest->update(['status' => SaleReturnRequest::MAIN_REJECTED, 'main_rejection_reason' => $data['reason'],
            'main_rejected_by_id' => $actor->id, 'main_rejected_by_name' => $actor->name, 'main_rejected_at' => now()]);

        return $this->success($saleReturnRequest, 'Kho chính đã từ chối yêu cầu.');
    }

    private function ensureInventorySuccess(Response $response): void
    {
        if (! $response->successful()) {
            abort(502, 'Inventory Service không thể cập nhật tồn kho: '.($response->json('error.message') ?? $response->body()));
        }
    }

    /** @return array<string, mixed> */
    private function ensureWarehouseType(int $warehouseId, bool $mustBeSellable, ?string $token): array
    {
        $response = Http::withToken((string) $token)
            ->timeout(config('services.inventory.timeout', 3))
            ->get(config('services.inventory.url').'/api/v1/inventory/warehouses');
        $this->ensureInventorySuccess($response);
        $warehouse = collect($response->json('data', []))->firstWhere('id', $warehouseId);
        if (! $warehouse || ! ($warehouse['is_active'] ?? false) || (bool) ($warehouse['can_sell'] ?? false) !== $mustBeSellable) {
            throw ValidationException::withMessages([
                'warehouse_id' => $mustBeSellable
                    ? 'Kho chính phải là kho đang hoạt động và được phép bán.'
                    : 'Kho kiểm tra phải là kho kỹ thuật đang hoạt động, không được phép bán.',
            ]);
        }

        return $warehouse;
    }

    private function allocatedQuantity(int $invoiceItemId): float
    {
        return (float) SaleReturnRequestItem::query()->where('invoice_item_id', $invoiceItemId)
            ->whereHas('request', fn ($query) => $query->where('status', '!=', SaleReturnRequest::REJECTED))
            ->sum('requested_quantity');
    }

    private function nextRequestNumber(string $customerCode): string
    {
        $digits = preg_replace('/\D+/', '', $customerCode) ?: str_pad((string) abs(crc32($customerCode)), 5, '0', STR_PAD_LEFT);
        $base = 'SRR-'.substr(str_pad($digits, 5, '0', STR_PAD_LEFT), -5).'-'.now()->format('dmy');
        $number = $base;
        for ($suffix = 2; SaleReturnRequest::where('request_number', $number)->exists(); $suffix++) {
            $number = "{$base}-{$suffix}";
        }

        return $number;
    }

    private function requireStatus(SaleReturnRequest $returnRequest, array $allowed): void
    {
        abort_unless(in_array($returnRequest->status, $allowed, true), 409, 'Trạng thái yêu cầu không cho phép thao tác này.');
    }

    private function loadRequest(SaleReturnRequest $returnRequest): SaleReturnRequest
    {
        return $returnRequest->fresh(['documents', 'items', 'attachments', 'saleReturn']);
    }

    private function success(SaleReturnRequest $returnRequest, string $message): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->loadRequest($returnRequest), 'message' => $message]);
    }
}
