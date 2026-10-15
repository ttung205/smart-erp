<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleReportItem;
use App\Models\SalesAttachment;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\SaleReportService;
use App\Support\DocumentDate;
use App\Support\DocumentNumberGenerator;
use App\Support\SalesWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = SalesOrder::with(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver'])
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

    public function show($id)
    {
        $so = SalesOrder::with(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver', 'attachments'])
            ->where('id', $id)
            ->orWhere('order_number', $id)
            ->first();

        if (! $so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        return response()->json(['success' => true, 'data' => $so]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', 'integer', 'exists:catalog.customers,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'order_date' => DocumentDate::rules(),
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:catalog.products,id'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.selling_price' => ['required', 'numeric', 'gte:0'],
            'items.*.vat_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        try {
            DB::beginTransaction();

            $customer = Customer::findOrFail($request->input('customer_id'));
            // Quy định số đơn bán: OD-mã khách hàng-ddmmyy (trùng trong ngày thêm -A, -B...)
            $odNumber = DocumentNumberGenerator::forCustomer(SalesOrder::class, 'order_number', 'OD', $customer->code);

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

            // Xử lý created_by / assigned_to dạng ID
            $createdBy = $request->attributes->get('actor')->id;
            $assignedTo = $this->resolveUserId($request->input('assigned_to'), $createdBy);

            $items = $request->input('items', []);
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
                'order_date' => DocumentDate::normalize($request->input('order_date'), now()->toDateString()),
                'currency' => $request->input('currency', 'VND'),
                'exchange_rate' => (float) $request->input('exchange_rate', 1.0),
                'created_by' => $createdBy,
                'assigned_to' => $assignedTo,
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total_amount' => $totalAmount,
                'status' => 'DRAFT',
                'notes' => $request->input('notes'),
                'shipping_address' => $request->input('shipping_address'),
            ]);
            foreach ($items as $it) {
                $prodId = $it['product_id'] ?? null;
                $prod = null;
                if ($prodId) {
                    $prod = Product::find($prodId);
                } elseif (! empty($it['sku'])) {
                    $prod = Product::where('sku', $it['sku'])->first();
                    $prodId = $prod?->id;
                }

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
                    $grossPrice = (float) ($prod->gross_price ?? 0);
                }

                SalesOrderItem::create([
                    'order_id' => $so->id,
                    'quotation_item_id' => $qItemId,
                    'product_id' => $prodId,
                    'sku' => $it['sku'] ?? $prod?->sku ?? 'UNKNOWN',
                    'product_name' => $it['product_name'] ?? $it['designation'] ?? $it['description'] ?? ($prod?->designation ?? 'Sản phẩm'),
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
                'data' => $so->load(['items.product', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver', 'attachments']),
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
            'customer_id' => ['sometimes', 'required', 'integer', 'exists:catalog.customers,id'],
            'quotation_id' => ['sometimes', 'nullable', 'integer', 'exists:quotations,id'],
            'order_date' => ['sometimes', ...DocumentDate::rules(required: true)],
            'exchange_rate' => ['sometimes', 'numeric', 'gt:0'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:catalog.products,id'],
            'items.*.sku' => ['required_with:items', 'string', 'max:100'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.selling_price' => ['required_with:items', 'numeric', 'gte:0'],
            'items.*.vat_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);

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

            $assignedTo = $this->resolveUserId(
                $request->input('assigned_to', $so->assigned_to),
                $request->attributes->get('actor')->id,
            );

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
                'order_date' => DocumentDate::normalize($request->input('order_date'), $so->order_date),
                'currency' => $request->input('currency', $so->currency),
                'exchange_rate' => (float) $request->input('exchange_rate', $so->exchange_rate),
                'assigned_to' => $assignedTo,
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total_amount' => $totalAmount,
                'status' => $so->status,
                'notes' => $request->input('notes', $so->notes),
                'shipping_address' => $request->input('shipping_address', $so->shipping_address),
            ]);

            if ($request->has('items')) {
                SalesOrderItem::where('order_id', $so->id)->delete();
                foreach ($request->input('items', []) as $it) {
                    $prodId = $it['product_id'] ?? null;
                    $prod = null;
                    if ($prodId) {
                        $prod = Product::find($prodId);
                    } elseif (! empty($it['sku'])) {
                        $prod = Product::where('sku', $it['sku'])->first();
                        $prodId = $prod?->id;
                    }

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
                        $grossPrice = (float) ($prod->gross_price ?? 0);
                    }

                    SalesOrderItem::create([
                        'order_id' => $so->id,
                        'quotation_item_id' => $qItemId,
                        'product_id' => $prodId,
                        'sku' => $it['sku'] ?? $prod?->sku ?? 'UNKNOWN',
                        'product_name' => $it['product_name'] ?? $it['designation'] ?? $it['description'] ?? ($prod?->designation ?? 'Sản phẩm'),
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
                'data' => $so->load(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver', 'attachments']),
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

    public function submitApproval($id)
    {
        $so = SalesOrder::where('id', $id)->orWhere('order_number', $id)->first();
        if (! $so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        if (! SalesWorkflow::canTransition('order', $so->status, 'PENDING_APPROVAL')) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATUS_TRANSITION']], 409);
        }
        $so->update(['status' => 'PENDING_APPROVAL']);

        return response()->json([
            'success' => true,
            'data' => $so->load(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver']),
            'message' => "Đơn bán hàng {$so->order_number} đã được gửi xét duyệt.",
        ]);
    }

    public function approve(Request $request, $id)
    {
        $so = SalesOrder::where('id', $id)->orWhere('order_number', $id)->first();
        if (! $so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        if (! SalesWorkflow::canTransition('order', $so->status, 'APPROVED')) {
            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_STATUS_TRANSITION']], 409);
        }
        $approverId = $request->attributes->get('actor')->id;

        $so->update([
            'status' => 'APPROVED',
            'approved_by' => $approverId,
            'approved_at' => now()->format('d/m/Y H:i'),
        ]);

        SaleReportService::syncOrderToReport($so);

        return response()->json([
            'success' => true,
            'data' => $so->load(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver']),
            'message' => "Đơn bán hàng {$so->order_number} đã được phê duyệt thành công.",
        ]);
    }

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
        $so->update([
            'status' => 'REJECTED',
            'notes' => $reason ? ($so->notes ? $so->notes."\n[Lý do từ chối: {$reason}]" : "[Lý do từ chối: {$reason}]") : $so->notes,
        ]);

        SaleReportItem::where('order_id', $so->id)->update(['status' => 'CANCELLED']);

        return response()->json([
            'success' => true,
            'data' => $so->load(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver']),
            'message' => "Đơn bán hàng {$so->order_number} đã bị từ chối.",
        ]);
    }

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

    private function resolveUserId(mixed $userInput, int $fallbackUserId): int
    {
        if (is_numeric($userInput) && (int) $userInput > 0) {
            return (int) $userInput;
        }

        if (is_string($userInput) && trim($userInput) !== '') {
            $user = User::where('name', trim($userInput))->orWhere('email', trim($userInput))->first();
            if ($user) {
                return $user->id;
            }
        }

        return $fallbackUserId;
    }
}
