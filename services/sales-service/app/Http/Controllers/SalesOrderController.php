<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleReportItem;
use App\Models\SalesAttachment;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\ReferenceSnapshot;
use App\Services\SaleReportService;
use App\Support\DocumentDate;
use App\Support\DocumentNumberGenerator;
use App\Support\Outbox;
use App\Support\SalesWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SalesOrderInput',
    required: ['customer_id', 'items'],
    properties: [
        new OA\Property(property: 'customer_id', type: 'integer', description: 'Mã, tên, mã số thuế khách hàng được snapshot từ catalog'),
        new OA\Property(property: 'contact_id', type: 'integer', nullable: true),
        new OA\Property(property: 'quotation_id', type: 'integer', nullable: true, description: 'Báo giá đã duyệt và chưa chuyển đơn; không đổi được sau khi tạo'),
        new OA\Property(property: 'quotation_number', type: 'string', nullable: true),
        new OA\Property(property: 'customer_order_number', type: 'string', nullable: true, description: 'Số PO của khách hàng'),
        new OA\Property(property: 'order_date', type: 'string', nullable: true, description: 'Y-m-d hoặc d/m/Y, mặc định hôm nay', example: '2026-11-03'),
        new OA\Property(property: 'currency', type: 'string', nullable: true, example: 'VND'),
        new OA\Property(property: 'exchange_rate', type: 'number', nullable: true, exclusiveMinimum: 0),
        new OA\Property(property: 'assigned_to', type: 'integer', nullable: true, description: 'Nhân viên phụ trách'),
        new OA\Property(property: 'shipping_address', type: 'string', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'attachment_ids', type: 'array', nullable: true, items: new OA\Items(type: 'integer')),
        new OA\Property(
            property: 'items',
            type: 'array',
            minItems: 1,
            items: new OA\Items(
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/SalesDocumentItem'),
                    new OA\Schema(
                        properties: [
                            new OA\Property(property: 'quotation_item_id', type: 'integer', nullable: true),
                            new OA\Property(property: 'product_name', type: 'string', nullable: true),
                            new OA\Property(property: 'delivery_date', type: 'string', format: 'date', nullable: true),
                        ],
                        type: 'object'
                    ),
                ]
            )
        ),
    ],
    type: 'object'
)]
class SalesOrderController extends Controller
{
    public function __construct(private readonly ReferenceSnapshot $snapshots) {}

    #[OA\Get(
        path: '/orders',
        summary: 'Danh sách đơn bán hàng',
        description: 'Yêu cầu quyền order.view.',
        tags: ['Đơn bán hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đơn bán và phân trang'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.view'),
        ]
    )]
    public function index(Request $request)
    {
        $orders = SalesOrder::with(['items', 'quotation'])
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/orders/{order}',
        summary: 'Chi tiết đơn bán hàng',
        description: 'Yêu cầu quyền order.view.',
        tags: ['Đơn bán hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, description: 'Id hoặc số đơn', schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Đơn bán kèm dòng hàng'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.view'),
            new OA\Response(response: 404, description: self::DOC_404),
        ]
    )]
    public function show($id)
    {
        $so = SalesOrder::with(['items', 'quotation', 'attachments'])
            ->where('id', $id)
            ->orWhere('order_number', $id)
            ->first();

        if (! $so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        return response()->json(['success' => true, 'data' => $so]);
    }

    #[OA\Post(
        path: '/orders',
        summary: 'Tạo đơn bán hàng',
        description: 'Yêu cầu quyền order.create. Đơn tạo ở trạng thái DRAFT; thông tin khách hàng và sản phẩm được snapshot tại thời điểm tạo.',
        tags: ['Đơn bán hàng'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SalesOrderInput')),
        responses: [
            new OA\Response(response: 201, description: 'Đơn bán mới'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.create'),
            new OA\Response(response: 409, description: 'QUOTATION_NOT_APPROVED hoặc QUOTATION_ALREADY_CONVERTED'),
            new OA\Response(response: 422, description: self::DOC_422),
            new OA\Response(response: 500, description: 'Không tạo được đơn bán'),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', 'integer'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'order_date' => DocumentDate::rules(),
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.selling_price' => ['required', 'numeric', 'gte:0'],
            'items.*.vat_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        // Lấy & snapshot dữ liệu khách hàng/sản phẩm/người phụ trách trước khi mở transaction
        $items = $request->input('items', []);
        $customer = $this->snapshots->customer((int) $request->input('customer_id'), $request->input('contact_id'));
        $products = $this->snapshots->products($items);
        $actor = $request->attributes->get('actor');
        $assignedTo = $this->snapshots->resolveUserId($request->input('assigned_to'), $actor->id);
        $assignedToName = $assignedTo === $actor->id ? $actor->name : $this->snapshots->userName($assignedTo);

        try {
            DB::beginTransaction();

            // Quy định số đơn bán: OD-mã khách hàng-ddmmyy (trùng trong ngày thêm -A, -B...)
            $odNumber = DocumentNumberGenerator::forCustomer(SalesOrder::class, 'order_number', 'OD', $customer['customer_code']);

            // Số đơn hàng của khách: nếu người dùng điền số khác thì dùng số đó, còn không thì tự động dùng số hệ thống
            $custOrderNo = trim($request->input('customer_order_number', ''));
            if (! $custOrderNo || str_starts_with($custOrderNo, '(Tự động') || str_starts_with($custOrderNo, '(Để trống')) {
                $custOrderNo = $odNumber;
            }

            // Xử lý quotation_id
            $quotationId = $request->input('quotation_id');
            if (! $quotationId && $request->filled('quotation_number')) {
                $q = Quotation::where('quotation_number', $request->input('quotation_number'))->first();
                if ($q) {
                    $quotationId = $q->id;
                }
            }
            if ($quotationId) {
                $quotation = Quotation::lockForUpdate()->find($quotationId);
                if (! $quotation || $quotation->status !== 'APPROVED') {
                    DB::rollBack();

                    return response()->json(['success' => false, 'error' => ['code' => 'QUOTATION_NOT_APPROVED']], 409);
                }
                if (SalesOrder::where('quotation_id', $quotationId)->exists()) {
                    DB::rollBack();

                    return response()->json(['success' => false, 'error' => ['code' => 'QUOTATION_ALREADY_CONVERTED']], 409);
                }
            }

            $createdBy = $actor->id;

            $calcSubtotal = 0;
            $calcVatTotal = 0;
            foreach ($items as $it) {
                $qty = (float) ($it['quantity'] ?? 1);
                $price = (float) ($it['selling_price'] ?? $it['unit_price'] ?? 0);
                $lineSub = $qty * $price;
                $vatP = (float) ($it['vat_percent'] ?? 10);
                $lineVat = round($lineSub * ($vatP / 100), 2);
                $calcSubtotal += $lineSub;
                $calcVatTotal += $lineVat;
            }

            $subtotal = $calcSubtotal;
            $vatTotal = $calcVatTotal;
            $totalAmount = $subtotal + $vatTotal;

            $so = SalesOrder::create([
                'order_number' => $odNumber,
                'customer_order_number' => $custOrderNo,
                'quotation_id' => $quotationId,
                'customer_id' => $request->input('customer_id'),
                'contact_id' => $request->input('contact_id'),
                ...$customer,
                'order_date' => DocumentDate::normalize($request->input('order_date'), now()->toDateString()),
                'currency' => $request->input('currency', 'VND'),
                'exchange_rate' => (float) $request->input('exchange_rate', 1.0),
                'created_by' => $createdBy,
                'created_by_name' => $actor->name,
                'assigned_to' => $assignedTo,
                'assigned_to_name' => $assignedToName,
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total_amount' => $totalAmount,
                'status' => 'DRAFT',
                'notes' => $request->input('notes'),
                'shipping_address' => $request->input('shipping_address'),
            ]);
            foreach ($items as $key => $it) {
                $prod = $products[$key];
                $prodId = $prod['id'] ?? null;

                $qItemId = $it['quotation_item_id'] ?? null;
                if (! $qItemId && $quotationId) {
                    $qItem = QuotationItem::where('quotation_id', $quotationId)
                        ->where(function ($q) use ($prodId, $it) {
                            if ($prodId) {
                                $q->where('product_id', $prodId);
                            }
                            if (! empty($it['sku'])) {
                                $q->orWhere('sku', $it['sku']);
                            }
                        })->first();
                    $qItemId = $qItem?->id;
                }

                $grossPrice = (float) ($it['gross_price'] ?? 0);
                if ($grossPrice <= 0 && $prod) {
                    $grossPrice = (float) ($prod['gross_price'] ?? 0);
                }

                SalesOrderItem::create([
                    'order_id' => $so->id,
                    'quotation_item_id' => $qItemId,
                    'product_id' => $prodId,
                    'sku' => $it['sku'] ?? $prod['sku'] ?? 'UNKNOWN',
                    'product_name' => $it['product_name'] ?? $it['designation'] ?? $it['description'] ?? ($prod['designation'] ?? 'Sản phẩm'),
                    ...ReferenceSnapshot::productColumns($prod),
                    'description' => $it['description'] ?? $prod['description'] ?? null,
                    'delivery_date' => $it['delivery_date'] ?? $it['expected_delivery_date'] ?? null,
                    'quantity' => (float) ($it['quantity'] ?? 1),
                    'gross_price' => $grossPrice,
                    'discount_percent' => (float) ($it['discount_percent'] ?? 0),
                    'selling_price' => (float) ($it['selling_price'] ?? $it['unit_price'] ?? 0),
                    'vat_percent' => (float) ($it['vat_percent'] ?? 10),
                    'lead_time' => $it['lead_time'] ?? 'Có sẵn',
                    'subtotal' => (float) $it['quantity'] * (float) ($it['selling_price'] ?? $it['unit_price']),
                    'status' => $it['status'] ?? 'WIN',
                ]);
            }

            // Nếu đơn hàng tạo từ báo giá, cập nhật trạng thái báo giá sang CONVERTED
            if ($quotationId) {
                Quotation::where('id', $quotationId)->update(['status' => 'CONVERTED']);
            }

            if ($so->status === 'APPROVED') {
                SaleReportService::syncOrderToReport($so);
            }

            // Liên kết tệp đính kèm nếu có
            if ($request->has('attachment_ids')) {
                SalesAttachment::whereIn('id', (array) $request->input('attachment_ids'))
                    ->whereNull('attachable_id')
                    ->where('uploaded_by', $request->attributes->get('actor')->name)
                    ->update([
                        'attachable_id' => $so->id,
                        'attachable_type' => 'sales_order',
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $so->load(['items', 'quotation', 'attachments']),
                'message' => "Tạo đơn bán hàng {$so->order_number} thành công.",
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo đơn bán hàng.',
            ], 500);
        }
    }

    #[OA\Put(
        path: '/orders/{order}',
        summary: 'Sửa đơn bán hàng',
        description: 'Yêu cầu quyền order.update. Chỉ đơn DRAFT hoặc REJECTED. Chấp nhận cả PUT và PATCH; gửi items thì thay toàn bộ dòng hàng. Không đổi được báo giá liên kết.',
        tags: ['Đơn bán hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SalesOrderInput')),
        responses: [
            new OA\Response(response: 200, description: 'Đơn bán sau khi sửa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.update'),
            new OA\Response(response: 404, description: 'Không tìm thấy đơn bán hàng'),
            new OA\Response(response: 409, description: 'DOCUMENT_LOCKED (đơn đã gửi duyệt) hoặc QUOTATION_LINK_IMMUTABLE'),
            new OA\Response(response: 422, description: self::DOC_422),
            new OA\Response(response: 500, description: 'Không cập nhật được đơn bán'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $so = SalesOrder::find($id);
        if (! $so) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn bán hàng'], 404);
        }
        if (! in_array($so->status, ['DRAFT', 'REJECTED'], true)) {
            return response()->json(['success' => false, 'error' => ['code' => 'DOCUMENT_LOCKED', 'message' => 'Chỉ được sửa đơn nháp hoặc bị từ chối.']], 409);
        }

        $request->validate([
            'customer_id' => ['sometimes', 'required', 'integer'],
            'quotation_id' => ['sometimes', 'nullable', 'integer', 'exists:quotations,id'],
            'order_date' => ['sometimes', ...DocumentDate::rules(required: true)],
            'exchange_rate' => ['sometimes', 'numeric', 'gt:0'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer'],
            'items.*.sku' => ['required_with:items', 'string', 'max:100'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.selling_price' => ['required_with:items', 'numeric', 'gte:0'],
            'items.*.vat_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $actor = $request->attributes->get('actor');
        $assignedTo = $this->snapshots->resolveUserId($request->input('assigned_to', $so->assigned_to), $actor->id);
        $assignedToName = (int) $assignedTo === (int) $so->assigned_to ? $so->assigned_to_name
            : ($assignedTo === $actor->id ? $actor->name : $this->snapshots->userName($assignedTo));
        // Đổi khách hàng / người liên hệ thì chụp lại snapshot
        $customer = [];
        if ($request->filled('customer_id') || $request->has('contact_id')) {
            $customer = $this->snapshots->customer(
                (int) $request->input('customer_id', $so->customer_id),
                $request->input('contact_id', $so->contact_id),
            );
        }
        $products = $request->has('items') ? $this->snapshots->products($request->input('items', [])) : [];

        try {
            DB::beginTransaction();

            $custOrderNo = trim($request->input('customer_order_number', ''));
            if (! $custOrderNo || str_starts_with($custOrderNo, '(Tự động') || str_starts_with($custOrderNo, '(Để trống')) {
                $custOrderNo = $so->order_number;
            }

            $quotationId = $request->input('quotation_id', $so->quotation_id);
            if (! $quotationId && $request->filled('quotation_number')) {
                $q = Quotation::where('quotation_number', $request->input('quotation_number'))->first();
                if ($q) {
                    $quotationId = $q->id;
                }
            }
            if ($quotationId && (int) $quotationId !== (int) $so->quotation_id) {
                DB::rollBack();

                return response()->json(['success' => false, 'error' => ['code' => 'QUOTATION_LINK_IMMUTABLE']], 409);
            }

            $calcSubtotal = 0;
            $calcVatTotal = 0;
            $items = $request->input('items', []);
            if (! empty($items)) {
                foreach ($items as $it) {
                    $qty = (float) ($it['quantity'] ?? 1);
                    $price = (float) ($it['selling_price'] ?? $it['unit_price'] ?? 0);
                    $lineSub = $qty * $price;
                    $vatP = (float) ($it['vat_percent'] ?? 10);
                    $lineVat = round($lineSub * ($vatP / 100), 2);
                    $calcSubtotal += $lineSub;
                    $calcVatTotal += $lineVat;
                }
            }

            $subtotal = empty($items) ? (float) $so->subtotal : $calcSubtotal;
            $vatTotal = empty($items) ? (float) $so->vat_total : $calcVatTotal;
            $totalAmount = $subtotal + $vatTotal;

            $so->update([
                'customer_order_number' => $custOrderNo,
                'quotation_id' => $quotationId,
                'customer_id' => $request->input('customer_id', $so->customer_id),
                'contact_id' => $request->input('contact_id', $so->contact_id),
                ...$customer,
                'order_date' => DocumentDate::normalize($request->input('order_date'), $so->order_date),
                'currency' => $request->input('currency', $so->currency),
                'exchange_rate' => (float) $request->input('exchange_rate', $so->exchange_rate),
                'assigned_to' => $assignedTo,
                'assigned_to_name' => $assignedToName,
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total_amount' => $totalAmount,
                'status' => $so->status,
                'notes' => $request->input('notes', $so->notes),
                'shipping_address' => $request->input('shipping_address', $so->shipping_address),
            ]);

            if ($request->has('items')) {
                SalesOrderItem::where('order_id', $so->id)->delete();
                foreach ($request->input('items', []) as $key => $it) {
                    $prod = $products[$key];
                    $prodId = $prod['id'] ?? null;

                    $qItemId = $it['quotation_item_id'] ?? null;
                    if (! $qItemId && $quotationId) {
                        $qItem = QuotationItem::where('quotation_id', $quotationId)
                            ->where(function ($q) use ($prodId, $it) {
                                if ($prodId) {
                                    $q->where('product_id', $prodId);
                                }
                                if (! empty($it['sku'])) {
                                    $q->orWhere('sku', $it['sku']);
                                }
                            })->first();
                        $qItemId = $qItem?->id;
                    }

                    $grossPrice = (float) ($it['gross_price'] ?? 0);
                    if ($grossPrice <= 0 && $prod) {
                        $grossPrice = (float) ($prod['gross_price'] ?? 0);
                    }

                    SalesOrderItem::create([
                        'order_id' => $so->id,
                        'quotation_item_id' => $qItemId,
                        'product_id' => $prodId,
                        'sku' => $it['sku'] ?? $prod['sku'] ?? 'UNKNOWN',
                        'product_name' => $it['product_name'] ?? $it['designation'] ?? $it['description'] ?? ($prod['designation'] ?? 'Sản phẩm'),
                        ...ReferenceSnapshot::productColumns($prod),
                        'description' => $it['description'] ?? $prod['description'] ?? null,
                        'delivery_date' => $it['delivery_date'] ?? $it['expected_delivery_date'] ?? null,
                        'quantity' => (float) ($it['quantity'] ?? 1),
                        'gross_price' => $grossPrice,
                        'discount_percent' => (float) ($it['discount_percent'] ?? 0),
                        'selling_price' => (float) ($it['selling_price'] ?? $it['unit_price'] ?? 0),
                        'vat_percent' => (float) ($it['vat_percent'] ?? 10),
                        'lead_time' => $it['lead_time'] ?? 'Có sẵn',
                        'subtotal' => (float) $it['quantity'] * (float) ($it['selling_price'] ?? $it['unit_price']),
                        'status' => $it['status'] ?? 'WIN',
                    ]);
                }
            }

            if (in_array($so->status, ['APPROVED', 'PROCESSING', 'COMPLETED'])) {
                SaleReportService::syncOrderToReport($so);
            }

            // Cập nhật liên kết tệp đính kèm
            if ($request->has('attachment_ids')) {
                SalesAttachment::whereIn('id', (array) $request->input('attachment_ids'))
                    ->where(function ($query) use ($so) {
                        $query->whereNull('attachable_id')->orWhere('attachable_id', $so->id);
                    })
                    ->where('uploaded_by', $request->attributes->get('actor')->name)
                    ->update([
                        'attachable_id' => $so->id,
                        'attachable_type' => 'sales_order',
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $so->load(['items', 'quotation', 'attachments']),
                'message' => "Cập nhật đơn bán hàng {$so->order_number} thành công.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Không thể cập nhật đơn bán hàng.',
            ], 500);
        }
    }

    #[OA\Post(
        path: '/orders/{id}/submit-approval',
        summary: 'Gửi duyệt đơn bán hàng',
        description: 'Yêu cầu quyền order.update.',
        tags: ['Đơn bán hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đơn chờ duyệt'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.update'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 409, description: self::DOC_409_STATUS),
        ]
    )]
    public function submitApproval(Request $request, $id)
    {
        $so = SalesOrder::where('id', $id)->orWhere('order_number', $id)->first();
        if (! $so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        if (! SalesWorkflow::canTransition('order', $so->status, 'PENDING_APPROVAL')) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATUS_TRANSITION']], 409);
        }
        DB::transaction(function () use ($request, $so): void {
            $so->update(['status' => 'PENDING_APPROVAL']);
            $this->recordEvent('SalesOrderSubmitted', 'sales.order.submitted', $so, $request);
        });

        return response()->json([
            'success' => true,
            'data' => $so->load(['items', 'quotation']),
            'message' => "Đơn bán hàng {$so->order_number} đã được gửi xét duyệt.",
        ]);
    }

    #[OA\Post(
        path: '/orders/{id}/approve',
        summary: 'Duyệt đơn bán hàng',
        description: 'Yêu cầu quyền order.approve. Đơn đã duyệt mới tạo được yêu cầu giao hàng.',
        tags: ['Đơn bán hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đơn đã duyệt'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.approve'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 409, description: self::DOC_409_STATUS),
        ]
    )]
    public function approve(Request $request, $id)
    {
        $so = SalesOrder::where('id', $id)->orWhere('order_number', $id)->first();
        if (! $so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        if (! SalesWorkflow::canTransition('order', $so->status, 'APPROVED')) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATUS_TRANSITION']], 409);
        }
        $approver = $request->attributes->get('actor');

        DB::transaction(function () use ($request, $so, $approver): void {
            $so->update([
                'status' => 'APPROVED',
                'approved_by' => $approver->id,
                'approved_by_name' => $approver->name,
                'approved_at' => now()->format('d/m/Y H:i'),
            ]);
            $this->recordEvent('SalesOrderApproved', 'sales.order.approved', $so, $request);
        });

        SaleReportService::syncOrderToReport($so);

        return response()->json([
            'success' => true,
            'data' => $so->load(['items', 'quotation']),
            'message' => "Đơn bán hàng {$so->order_number} đã được phê duyệt thành công.",
        ]);
    }

    #[OA\Post(
        path: '/orders/{id}/reject',
        summary: 'Từ chối đơn bán hàng',
        description: 'Yêu cầu quyền order.approve. Bắt buộc ghi lý do.',
        tags: ['Đơn bán hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string')])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Đơn bị từ chối'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.approve'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 409, description: self::DOC_409_STATUS),
            new OA\Response(response: 422, description: 'REJECTION_REASON_REQUIRED: thiếu lý do'),
        ]
    )]
    public function reject(Request $request, $id)
    {
        $so = SalesOrder::where('id', $id)->orWhere('order_number', $id)->first();
        if (! $so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        if (! SalesWorkflow::canTransition('order', $so->status, 'REJECTED')) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATUS_TRANSITION']], 409);
        }
        $reason = trim($request->input('reason', ''));
        if ($reason === '') {
            return response()->json(['success' => false, 'error' => ['code' => 'REJECTION_REASON_REQUIRED']], 422);
        }
        DB::transaction(function () use ($request, $so, $reason): void {
            $so->update([
                'status' => 'REJECTED',
                'notes' => $reason ? ($so->notes ? $so->notes."\n[Lý do từ chối: {$reason}]" : "[Lý do từ chối: {$reason}]") : $so->notes,
            ]);
            $this->recordEvent('SalesOrderRejected', 'sales.order.rejected', $so, $request, ['reason' => $reason]);
        });

        SaleReportItem::where('order_id', $so->id)->update(['status' => 'CANCELLED']);

        return response()->json([
            'success' => true,
            'data' => $so->load(['items', 'quotation']),
            'message' => "Đơn bán hàng {$so->order_number} đã bị từ chối.",
        ]);
    }

    #[OA\Delete(
        path: '/orders/{order}',
        summary: 'Xóa đơn bán hàng',
        description: 'Yêu cầu quyền order.cancel. Chỉ đơn DRAFT hoặc REJECTED; xóa luôn các dòng báo cáo bán hàng của đơn.',
        tags: ['Đơn bán hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.cancel'),
            new OA\Response(response: 404, description: 'Không tìm thấy đơn hàng'),
            new OA\Response(response: 409, description: 'DOCUMENT_LOCKED: đơn đã gửi duyệt hoặc đã duyệt'),
        ]
    )]
    public function destroy($id)
    {
        $so = SalesOrder::find($id);
        if (! $so) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }
        if (! in_array($so->status, ['DRAFT', 'REJECTED'], true)) {
            return response()->json(['success' => false, 'error' => ['code' => 'DOCUMENT_LOCKED']], 409);
        }
        SaleReportItem::where('order_id', $so->id)->delete();
        $so->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa đơn bán hàng thành công']);
    }

    /** @param  array<string, mixed>  $extra */
    private function recordEvent(string $eventType, string $routingKey, SalesOrder $so, Request $request, array $extra = []): void
    {
        Outbox::record($eventType, $routingKey, 'SalesOrder', $so->id, [
            'order_id' => $so->id,
            'order_number' => $so->order_number,
            'customer_name' => $so->customer_name,
            'total_amount' => (float) $so->total_amount,
            'currency' => $so->currency,
            'created_by_id' => is_numeric($so->created_by) ? (int) $so->created_by : null,
            'actor' => Outbox::actor($request->attributes->get('actor')),
            ...$extra,
        ]);
    }
}
