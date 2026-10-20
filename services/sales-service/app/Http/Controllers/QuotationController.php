<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesAttachment;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\ReferenceSnapshot;
use App\Support\DocumentDate;
use App\Support\DocumentNumberGenerator;
use App\Support\Outbox;
use App\Support\SalesWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class QuotationController extends Controller
{
    public function __construct(private readonly ReferenceSnapshot $snapshots) {}

    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $status = $request->input('status');

        $query = Quotation::with('items')->orderBy('id', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));
        $quotations = collect($paginator->items())->map(function ($q) {
            return [
                'id' => $q->id,
                'quotation_number' => $q->quotation_number,
                'customer_id' => $q->customer_id,
                'customer_code' => $q->customer_code,
                'customer_name' => $q->customer_name,
                'contact_id' => $q->contact_id,
                'contact_name' => $q->contact_name,
                'quotation_date' => $q->quotation_date,
                'created_at' => $q->created_at ? $q->created_at->format('d/m/Y H:i') : null,
                'created_by' => $q->created_by,
                'assigned_to' => $q->assigned_to,
                'currency' => $q->currency,
                'exchange_rate' => $q->exchange_rate,
                'status' => $q->status,
                'approved_by' => $q->approved_by,
                'approved_at' => $q->approved_at,
                'subtotal' => (float) $q->subtotal,
                'vat_total' => (float) $q->vat_total,
                'total_amount' => (float) $q->total_amount,
                'items_count' => $q->items->count(),
                'items' => $q->items,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $quotations,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $q = Quotation::with(['items', 'attachments'])
            ->where('id', $id)
            ->orWhere('quotation_number', $id)
            ->first();

        if (! $q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy báo giá']], 404);
        }

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', 'integer'],
            // Form báo giá không có ô ngày: bỏ trống thì lấy ngày tạo (hôm nay)
            'quotation_date' => DocumentDate::rules(),
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.selling_price' => ['required', 'numeric', 'gte:0'],
            'items.*.vat_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        // Lấy & snapshot dữ liệu khách hàng/sản phẩm trước khi mở transaction (không gọi HTTP trong transaction)
        $items = $request->input('items', []);
        $customer = $this->snapshots->customer($request->integer('customer_id'), $request->input('contact_id'));
        $products = $this->snapshots->products($items);

        DB::beginTransaction();
        try {
            // Quy định số báo giá: QT-mã khách hàng-ddmmyy (trùng trong ngày thêm -A, -B...)
            $qNumber = DocumentNumberGenerator::forCustomer(Quotation::class, 'quotation_number', 'QT', $customer['customer_code']);

            // Nếu người dùng chủ động truyền số báo giá tùy chỉnh hợp lệ (khác auto placeholder)
            $customNumber = trim($request->input('quotation_number', ''));
            if ($customNumber && ! str_starts_with($customNumber, '(Tự động') && ! Quotation::where('quotation_number', $customNumber)->exists()) {
                $qNumber = $customNumber;
            }

            $subtotal = collect($items)->sum(fn (array $item): float => (float) $item['quantity'] * (float) $item['selling_price']);
            $vatTotal = collect($items)->sum(fn (array $item): float => round((float) $item['quantity'] * (float) $item['selling_price'] * ((float) ($item['vat_percent'] ?? 10) / 100), 2));
            $actor = $request->attributes->get('actor');

            $quotation = Quotation::create([
                'quotation_number' => $qNumber,
                'customer_id' => $request->input('customer_id'),
                'contact_id' => $request->input('contact_id'),
                ...$customer,
                'quotation_date' => DocumentDate::normalize($request->input('quotation_date'), now()->toDateString()),
                'currency' => $request->input('currency', 'VND'),
                'exchange_rate' => (float) $request->input('exchange_rate', 1.0),
                'created_by' => $actor->name,
                'created_by_id' => $actor->id,
                'assigned_to' => $request->input('assigned_to', $actor->name),
                'status' => 'DRAFT',
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total_amount' => $subtotal + $vatTotal,
                'notes' => $request->input('notes'),
            ]);

            foreach ($items as $key => $it) {
                $prod = $products[$key];
                $grossPrice = (float) ($it['gross_price'] ?? 0);
                if ($grossPrice <= 0 && $prod) {
                    $grossPrice = (float) ($prod['gross_price'] ?? 0);
                }

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $prod['id'] ?? null,
                    'sku' => $it['sku'] ?? $prod['sku'] ?? 'UNKNOWN',
                    ...ReferenceSnapshot::productColumns($prod),
                    'description' => $it['description'] ?? $prod['description'] ?? null,
                    'quantity' => (float) ($it['quantity'] ?? 1),
                    'gross_price' => $grossPrice,
                    'discount_percent' => (float) ($it['discount_percent'] ?? 0),
                    'selling_price' => (float) ($it['selling_price'] ?? 0),
                    'vat_percent' => (float) ($it['vat_percent'] ?? 10),
                    'lead_time' => $it['lead_time'] ?? 'Có sẵn',
                    'subtotal' => (float) $it['quantity'] * (float) $it['selling_price'],
                    'won_quantity' => (float) ($it['won_quantity'] ?? 0),
                    'status' => $it['status'] ?? (($it['won'] ?? false) ? 'WIN' : 'PENDING'),
                ]);
            }

            // Liên kết tệp đính kèm nếu có
            if ($request->has('attachment_ids')) {
                SalesAttachment::whereIn('id', (array) $request->input('attachment_ids'))
                    ->whereNull('attachable_id')
                    ->where('uploaded_by', $request->attributes->get('actor')->name)
                    ->update([
                        'attachable_id' => $quotation->id,
                        'attachable_type' => 'quotation',
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $quotation->load(['items', 'attachments']),
                'message' => "Tạo báo giá {$quotation->quotation_number} thành công.",
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['success' => false, 'error' => ['code' => 'STORE_FAILED', 'message' => 'Không thể tạo báo giá.']], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $q = Quotation::where('id', $id)->orWhere('quotation_number', $id)->first();
        if (! $q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy báo giá']], 404);
        }
        if (! in_array($q->status, ['DRAFT', 'REJECTED'], true)) {
            return response()->json(['success' => false, 'error' => ['code' => 'DOCUMENT_LOCKED', 'message' => 'Chỉ được sửa báo giá nháp hoặc bị từ chối.']], 409);
        }

        $request->validate([
            'customer_id' => ['sometimes', 'required', 'integer'],
            'exchange_rate' => ['sometimes', 'numeric', 'gt:0'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer'],
            'items.*.sku' => ['required_with:items', 'string', 'max:100'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.selling_price' => ['required_with:items', 'numeric', 'gte:0'],
            'items.*.vat_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $items = $request->input('items');
        $subtotal = (float) $q->subtotal;
        $vatTotal = (float) $q->vat_total;
        if (is_array($items)) {
            $subtotal = collect($items)->sum(fn (array $item): float => (float) $item['quantity'] * (float) $item['selling_price']);
            $vatTotal = collect($items)->sum(fn (array $item): float => round((float) $item['quantity'] * (float) $item['selling_price'] * ((float) ($item['vat_percent'] ?? 10) / 100), 2));
        }

        // Đổi khách hàng / người liên hệ thì chụp lại snapshot
        $customer = [];
        if ($request->filled('customer_id') || $request->has('contact_id')) {
            $customer = $this->snapshots->customer(
                (int) $request->input('customer_id', $q->customer_id),
                $request->input('contact_id', $q->contact_id),
            );
        }
        $products = is_array($items) ? $this->snapshots->products($items) : [];

        DB::beginTransaction();
        try {
            $q->update([
                'customer_id' => $request->input('customer_id', $q->customer_id),
                'contact_id' => $request->input('contact_id', $q->contact_id),
                ...$customer,
                'currency' => $request->input('currency', $q->currency),
                'exchange_rate' => (float) $request->input('exchange_rate', $q->exchange_rate),
                'assigned_to' => $request->input('assigned_to', $q->assigned_to),
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total_amount' => $subtotal + $vatTotal,
                'notes' => $request->input('notes', $q->notes),
            ]);

            // Cập nhật liên kết tệp đính kèm
            if ($request->has('attachment_ids')) {
                SalesAttachment::whereIn('id', (array) $request->input('attachment_ids'))
                    ->where(function ($query) use ($q) {
                        $query->whereNull('attachable_id')->orWhere('attachable_id', $q->id);
                    })
                    ->where('uploaded_by', $request->attributes->get('actor')->name)
                    ->update([
                        'attachable_id' => $q->id,
                        'attachable_type' => 'quotation',
                    ]);
            }

            if (is_array($items)) {
                QuotationItem::where('quotation_id', $q->id)->delete();
                foreach ($items as $key => $it) {
                    $prod = $products[$key];
                    $grossPrice = (float) ($it['gross_price'] ?? 0);
                    if ($grossPrice <= 0 && $prod) {
                        $grossPrice = (float) ($prod['gross_price'] ?? 0);
                    }

                    QuotationItem::create([
                        'quotation_id' => $q->id,
                        'product_id' => $prod['id'] ?? null,
                        'sku' => $it['sku'] ?? $prod['sku'] ?? 'UNKNOWN',
                        ...ReferenceSnapshot::productColumns($prod),
                        'description' => $it['description'] ?? $prod['description'] ?? null,
                        'quantity' => (float) ($it['quantity'] ?? 1),
                        'gross_price' => $grossPrice,
                        'discount_percent' => (float) ($it['discount_percent'] ?? 0),
                        'selling_price' => (float) ($it['selling_price'] ?? 0),
                        'vat_percent' => (float) ($it['vat_percent'] ?? 10),
                        'lead_time' => $it['lead_time'] ?? 'Có sẵn',
                        'subtotal' => (float) $it['quantity'] * (float) $it['selling_price'],
                        'won_quantity' => (float) ($it['won_quantity'] ?? 0),
                        'status' => $it['status'] ?? (($it['won'] ?? false) ? 'WIN' : 'PENDING'),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $q->load('items'),
                'message' => "Cập nhật báo giá {$q->quotation_number} thành công.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['success' => false, 'error' => ['code' => 'UPDATE_FAILED', 'message' => 'Khong the cap nhat bao gia.']], 500);
        }
    }

    public function submitApproval(Request $request, $id)
    {
        $q = Quotation::where('id', $id)->orWhere('quotation_number', $id)->first();
        if (! $q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        if (! SalesWorkflow::canTransition('quotation', $q->status, 'PENDING_APPROVAL')) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATUS_TRANSITION']], 409);
        }
        DB::transaction(function () use ($request, $q): void {
            $q->update(['status' => 'PENDING_APPROVAL']);
            $this->recordEvent('QuotationSubmitted', 'sales.quotation.submitted', $q, $request);
        });

        return response()->json([
            'success' => true,
            'data' => $q,
            'message' => "Báo giá {$q->quotation_number} đã được gửi xét duyệt.",
        ]);
    }

    public function approve(Request $request, $id)
    {
        $q = Quotation::where('id', $id)->orWhere('quotation_number', $id)->first();
        if (! $q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        if (! SalesWorkflow::canTransition('quotation', $q->status, 'APPROVED')) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATUS_TRANSITION']], 409);
        }
        $approverName = $request->attributes->get('actor')->name;
        DB::transaction(function () use ($request, $q, $approverName): void {
            $q->update([
                'status' => 'APPROVED',
                'approved_by' => $approverName,
                'approved_at' => now()->format('d/m/Y H:i'),
            ]);
            $this->recordEvent('QuotationApproved', 'sales.quotation.approved', $q, $request);
        });

        return response()->json([
            'success' => true,
            'data' => $q,
            'message' => "Báo giá {$q->quotation_number} đã được phê duyệt thành công.",
        ]);
    }

    public function reject(Request $request, $id)
    {
        $q = Quotation::where('id', $id)->orWhere('quotation_number', $id)->first();
        if (! $q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        if (! SalesWorkflow::canTransition('quotation', $q->status, 'REJECTED')) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATUS_TRANSITION']], 409);
        }
        $reason = trim($request->input('reason', ''));
        if ($reason === '') {
            return response()->json(['success' => false, 'error' => ['code' => 'REJECTION_REASON_REQUIRED']], 422);
        }
        DB::transaction(function () use ($request, $q, $reason): void {
            $q->update([
                'status' => 'REJECTED',
                'notes' => $reason ? ($q->notes ? $q->notes."\n[Lý do từ chối: {$reason}]" : "[Lý do từ chối: {$reason}]") : $q->notes,
            ]);
            $this->recordEvent('QuotationRejected', 'sales.quotation.rejected', $q, $request, ['reason' => $reason]);
        });

        return response()->json([
            'success' => true,
            'data' => $q,
            'message' => "Báo giá {$q->quotation_number} đã bị từ chối.",
        ]);
    }

    public function createSO(Request $request, $id)
    {
        $q = Quotation::with('items')->where('id', $id)->orWhere('quotation_number', $id)->first();
        if (! $q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }
        if (! SalesWorkflow::canTransition('quotation', $q->status, 'CONVERTED') && ! SalesOrder::where('quotation_id', $q->id)->exists()) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATUS_TRANSITION', 'message' => 'Chỉ báo giá đã duyệt mới được chuyển thành đơn bán hàng.']], 409);
        }
        $existingOrder = SalesOrder::where('quotation_id', $q->id)->first();
        if ($existingOrder) {
            return response()->json(['success' => true, 'data' => $existingOrder->load('items'), 'message' => 'Báo giá đã được chuyển thành đơn bán hàng trước đó.']);
        }

        $actor = $request->attributes->get('actor');
        $assigneeId = is_numeric($q->assigned_to) ? (int) $q->assigned_to : $actor->id;
        $assigneeName = $assigneeId === $actor->id ? $actor->name : $this->snapshots->userName($assigneeId);

        DB::beginTransaction();
        try {
            $q = Quotation::with('items')->lockForUpdate()->findOrFail($q->id);
            $existingOrder = SalesOrder::where('quotation_id', $q->id)->first();
            if ($existingOrder) {
                DB::commit();

                return response()->json(['success' => true, 'data' => $existingOrder->load('items'), 'message' => 'Bao gia da duoc chuyen thanh don ban hang truoc do.']);
            }
            if (! SalesWorkflow::canTransition('quotation', $q->status, 'CONVERTED')) {
                abort(409, 'Chi bao gia da duyet moi duoc chuyen thanh don ban hang.');
            }

            $wonItems = $q->items->where('is_won', true);
            if ($request->has('items')) {
                $requestedIds = collect($request->input('items', []))
                    ->map(fn (mixed $item): ?int => (int) (is_array($item) ? ($item['id'] ?? $item['quotation_item_id'] ?? 0) : 0))
                    ->filter()
                    ->unique();
                if ($requestedIds->isEmpty()) {
                    abort(422, 'Danh sach mat hang bao gia khong hop le.');
                }
                $wonItems = $q->items->whereIn('id', $requestedIds);
                if ($wonItems->count() !== $requestedIds->count()) {
                    abort(422, 'Co mat hang khong thuoc bao gia nay.');
                }
            }
            if ($wonItems->isEmpty()) {
                abort(422, 'Bao gia chua co mat hang thang de tao don ban hang.');
            }

            $subtotal = $wonItems->sum(function (QuotationItem $item): float {
                $quantity = (float) ($item->won_quantity ?: $item->quantity);

                return $quantity * (float) $item->selling_price;
            });
            $vatTotal = $wonItems->sum(function (QuotationItem $item): float {
                $lineSubtotal = (float) ($item->won_quantity ?: $item->quantity) * (float) $item->selling_price;

                return round($lineSubtotal * ((float) $item->vat_percent / 100), 2);
            });

            // Đơn sinh từ báo giá theo cùng quy định với đơn lập tay: OD-mã khách hàng-ddmmyy
            $soNumber = DocumentNumberGenerator::forCustomer(SalesOrder::class, 'order_number', 'OD', $q->customer_code);

            $so = SalesOrder::create([
                'order_number' => $soNumber,
                'customer_order_number' => $soNumber,
                'quotation_id' => $q->id,
                'customer_id' => $q->customer_id,
                'contact_id' => $q->contact_id,
                ...$q->only([
                    'customer_code', 'customer_name', 'customer_tax_code', 'customer_address', 'customer_phone', 'customer_email',
                    'contact_name', 'contact_phone', 'contact_email',
                ]),
                'order_date' => now()->toDateString(),
                'currency' => $q->currency ?? 'VND',
                'exchange_rate' => (float) ($q->exchange_rate ?? 1.0),
                'created_by' => $actor->id,
                'created_by_name' => $actor->name,
                'assigned_to' => $assigneeId,
                'assigned_to_name' => $assigneeName,
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total_amount' => $subtotal + $vatTotal,
                'status' => 'DRAFT',
                'notes' => "Sinh tự động từ Báo Giá {$q->quotation_number}",
            ]);

            foreach ($wonItems as $it) {
                $qty = (float) ($it->won_quantity ?: $it->quantity);
                $sellingPrice = (float) $it->selling_price;

                SalesOrderItem::create([
                    'order_id' => $so->id,
                    'quotation_item_id' => $it->id,
                    'product_id' => $it->product_id,
                    'sku' => $it->sku ?: 'UNKNOWN',
                    'product_name' => ($it->designation ?: $it->description) ?: 'Sản phẩm',
                    'designation' => $it->designation,
                    'brand' => $it->brand,
                    'description' => $it->description,
                    'com' => $it->getRawOriginal('com'),
                    'unit' => $it->getRawOriginal('unit'),
                    'delivery_date' => $it->delivery_date ?? null,
                    'quantity' => $qty,
                    'gross_price' => (float) $it->gross_price,
                    'discount_percent' => (float) $it->discount_percent,
                    'selling_price' => $sellingPrice,
                    'vat_percent' => (float) $it->vat_percent,
                    'lead_time' => $it->lead_time ?: 'Có sẵn',
                    'subtotal' => $qty * $sellingPrice,
                    'status' => 'WIN',
                ]);
            }

            $q->update(['status' => 'CONVERTED']);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $so->load('items'),
                'message' => "Đã sinh Đơn Bán Hàng {$so->order_number} thành công từ Item Win.",
            ], 201);
        } catch (HttpExceptionInterface $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['success' => false, 'error' => ['code' => 'SO_FAILED', 'message' => 'Không thể tạo đơn bán hàng từ báo giá.']], 500);
        }
    }

    public function destroy($id)
    {
        $q = Quotation::where('id', $id)->orWhere('quotation_number', $id)->first();
        if (! $q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }
        if (! in_array($q->status, ['DRAFT', 'REJECTED'], true)) {
            return response()->json(['success' => false, 'error' => ['code' => 'DOCUMENT_LOCKED']], 409);
        }

        $q->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa báo giá thành công.']);
    }

    /** @param  array<string, mixed>  $extra */
    private function recordEvent(string $eventType, string $routingKey, Quotation $q, Request $request, array $extra = []): void
    {
        Outbox::record($eventType, $routingKey, 'Quotation', $q->id, [
            'quotation_id' => $q->id,
            'quotation_number' => $q->quotation_number,
            'customer_name' => $q->customer_name,
            'total_amount' => (float) $q->total_amount,
            'currency' => $q->currency,
            'created_by_id' => $q->created_by_id ? (int) $q->created_by_id : null,
            'actor' => Outbox::actor($request->attributes->get('actor')),
            ...$extra,
        ]);
    }
}
