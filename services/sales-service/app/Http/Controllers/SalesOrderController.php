<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Quotation;
use App\Models\Customer;
use App\Models\User;
use App\Models\SaleReportItem;
use App\Services\SaleReportService;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = SalesOrder::with(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function show($id)
    {
        $so = SalesOrder::with(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver'])
            ->where('id', $id)
            ->orWhere('order_number', $id)
            ->first();

        if (!$so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        return response()->json(['success' => true, 'data' => $so]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $customer = Customer::find($request->input('customer_id'));
            $customerCode = strtoupper(trim($customer ? $customer->code : ($request->input('customer_code') ?: 'CUST')));
            $dateStr = now()->format('dmy'); // ddmmyy e.g. 300926
            $baseNumber = "OD-{$customerCode}-{$dateStr}";

            // Quy định số đơn hàng nội bộ: OD-mã khách hàng-ddmmyy
            if (!SalesOrder::where('order_number', $baseNumber)->exists()) {
                $odNumber = $baseNumber;
            } else {
                $letters = range('A', 'Z');
                $letterIdx = 0;
                do {
                    $letterSuffix = $letters[$letterIdx] ?? ('Z' . ($letterIdx - 25));
                    $odNumber = "{$baseNumber}-{$letterSuffix}";
                    $letterIdx++;
                } while (SalesOrder::where('order_number', $odNumber)->exists());
            }

            // Số đơn hàng của khách: nếu người dùng điền số khác thì dùng số đó, còn không thì tự động dùng số hệ thống
            $custOrderNo = trim($request->input('customer_order_number', ''));
            if (!$custOrderNo || str_starts_with($custOrderNo, '(Tự động') || str_starts_with($custOrderNo, '(Để trống')) {
                $custOrderNo = $odNumber;
            }

            // Xử lý quotation_id
            $quotationId = $request->input('quotation_id');
            if (!$quotationId && $request->filled('quotation_number')) {
                $q = Quotation::where('quotation_number', $request->input('quotation_number'))->first();
                if ($q) {
                    $quotationId = $q->id;
                }
            }

            // Xử lý created_by / assigned_to dạng ID
            $createdBy = $this->resolveUserId($request->input('created_by'));
            $assignedTo = $this->resolveUserId($request->input('assigned_to'));

            $items = $request->input('items', []);
            $calcSubtotal = 0;
            $calcVatTotal = 0;
            foreach ($items as $it) {
                $qty = (float)($it['quantity'] ?? 1);
                $price = (float)($it['selling_price'] ?? $it['unit_price'] ?? 0);
                $lineSub = (float)($it['subtotal'] ?? 0);
                if ($lineSub <= 0) {
                    $lineSub = $qty * $price;
                }
                $vatP = (float)($it['vat_percent'] ?? 10);
                $lineVat = round($lineSub * ($vatP / 100), 2);
                $calcSubtotal += $lineSub;
                $calcVatTotal += $lineVat;
            }

            $subtotal = (float)$request->input('subtotal', 0);
            if ($subtotal <= 0 && $calcSubtotal > 0) $subtotal = $calcSubtotal;

            $vatTotal = (float)$request->input('vat_total', 0);
            if ($vatTotal <= 0 && $calcVatTotal > 0) $vatTotal = $calcVatTotal;

            $totalAmount = (float)$request->input('total_amount', 0);
            if ($totalAmount <= 0 && ($subtotal + $vatTotal) > 0) $totalAmount = $subtotal + $vatTotal;

            $so = SalesOrder::create([
                'order_number' => $odNumber,
                'customer_order_number' => $custOrderNo,
                'quotation_id' => $quotationId,
                'customer_id' => $request->input('customer_id'),
                'contact_id' => $request->input('contact_id'),
                'order_date' => $request->input('order_date', now()->format('d/m/Y')),
                'currency' => $request->input('currency', 'VND'),
                'exchange_rate' => (float)$request->input('exchange_rate', 1.0),
                'created_by' => $createdBy,
                'assigned_to' => $assignedTo,
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total_amount' => $totalAmount,
                'status' => $request->input('status', 'DRAFT'),
                'notes' => $request->input('notes'),
                'shipping_address' => $request->input('shipping_address'),
            ]);
            foreach ($items as $it) {
                $prodId = $it['product_id'] ?? null;
                $prod = null;
                if ($prodId) {
                    $prod = \App\Models\Product::find($prodId);
                } elseif (!empty($it['sku'])) {
                    $prod = \App\Models\Product::where('sku', $it['sku'])->first();
                    $prodId = $prod?->id;
                }

                $qItemId = $it['quotation_item_id'] ?? null;
                if (!$qItemId && $quotationId) {
                    $qItem = \App\Models\QuotationItem::where('quotation_id', $quotationId)
                        ->where(function ($q) use ($prodId, $it) {
                            if ($prodId) $q->where('product_id', $prodId);
                            if (!empty($it['sku'])) $q->orWhere('sku', $it['sku']);
                        })->first();
                    $qItemId = $qItem?->id;
                }

                $grossPrice = (float)($it['gross_price'] ?? 0);
                if ($grossPrice <= 0 && $prod) {
                    $grossPrice = (float)($prod->gross_price ?? 0);
                }

                SalesOrderItem::create([
                    'order_id' => $so->id,
                    'quotation_item_id' => $qItemId,
                    'product_id' => $prodId,
                    'sku' => $it['sku'] ?? $prod?->sku ?? 'UNKNOWN',
                    'product_name' => $it['product_name'] ?? $it['designation'] ?? $it['description'] ?? ($prod?->designation ?? 'Sản phẩm'),
                    'delivery_date' => $it['delivery_date'] ?? $it['expected_delivery_date'] ?? null,
                    'quantity' => (float)($it['quantity'] ?? 1),
                    'gross_price' => $grossPrice,
                    'discount_percent' => (float)($it['discount_percent'] ?? 0),
                    'selling_price' => (float)($it['selling_price'] ?? $it['unit_price'] ?? 0),
                    'vat_percent' => (float)($it['vat_percent'] ?? 10),
                    'lead_time' => $it['lead_time'] ?? 'Có sẵn',
                    'subtotal' => (float)($it['subtotal'] ?? 0),
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

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $so->load(['items.product', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver']),
                'message' => "Tạo đơn bán hàng {$so->order_number} thành công."
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi tạo đơn bán hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $so = SalesOrder::find($id);
        if (!$so) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn bán hàng'], 404);
        }

        try {
            DB::beginTransaction();

            $custOrderNo = trim($request->input('customer_order_number', ''));
            if (!$custOrderNo || str_starts_with($custOrderNo, '(Tự động') || str_starts_with($custOrderNo, '(Để trống')) {
                $custOrderNo = $so->order_number;
            }

            $quotationId = $request->input('quotation_id', $so->quotation_id);
            if (!$quotationId && $request->filled('quotation_number')) {
                $q = Quotation::where('quotation_number', $request->input('quotation_number'))->first();
                if ($q) {
                    $quotationId = $q->id;
                }
            }

            $assignedTo = $this->resolveUserId($request->input('assigned_to', $so->assigned_to));

            $calcSubtotal = 0;
            $calcVatTotal = 0;
            $items = $request->input('items', []);
            if (!empty($items)) {
                foreach ($items as $it) {
                    $qty = (float)($it['quantity'] ?? 1);
                    $price = (float)($it['selling_price'] ?? $it['unit_price'] ?? 0);
                    $lineSub = (float)($it['subtotal'] ?? 0);
                    if ($lineSub <= 0) {
                        $lineSub = $qty * $price;
                    }
                    $vatP = (float)($it['vat_percent'] ?? 10);
                    $lineVat = round($lineSub * ($vatP / 100), 2);
                    $calcSubtotal += $lineSub;
                    $calcVatTotal += $lineVat;
                }
            }

            $subtotal = (float)$request->input('subtotal', $so->subtotal);
            if ($subtotal <= 0 && $calcSubtotal > 0) $subtotal = $calcSubtotal;

            $vatTotal = (float)$request->input('vat_total', $so->vat_total);
            if ($vatTotal <= 0 && $calcVatTotal > 0) $vatTotal = $calcVatTotal;

            $totalAmount = (float)$request->input('total_amount', $so->total_amount);
            if ($totalAmount <= 0 && ($subtotal + $vatTotal) > 0) $totalAmount = $subtotal + $vatTotal;

            $so->update([
                'customer_order_number' => $custOrderNo,
                'quotation_id' => $quotationId,
                'customer_id' => $request->input('customer_id', $so->customer_id),
                'contact_id' => $request->input('contact_id', $so->contact_id),
                'order_date' => $request->input('order_date', $so->order_date),
                'currency' => $request->input('currency', $so->currency),
                'exchange_rate' => (float)$request->input('exchange_rate', $so->exchange_rate),
                'assigned_to' => $assignedTo,
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total_amount' => $totalAmount,
                'status' => $request->input('status', $so->status),
                'notes' => $request->input('notes', $so->notes),
                'shipping_address' => $request->input('shipping_address', $so->shipping_address),
            ]);

            if ($request->has('items')) {
                SalesOrderItem::where('order_id', $so->id)->delete();
                foreach ($request->input('items', []) as $it) {
                    $prodId = $it['product_id'] ?? null;
                    $prod = null;
                    if ($prodId) {
                        $prod = \App\Models\Product::find($prodId);
                    } elseif (!empty($it['sku'])) {
                        $prod = \App\Models\Product::where('sku', $it['sku'])->first();
                        $prodId = $prod?->id;
                    }

                    $qItemId = $it['quotation_item_id'] ?? null;
                    if (!$qItemId && $quotationId) {
                        $qItem = \App\Models\QuotationItem::where('quotation_id', $quotationId)
                            ->where(function ($q) use ($prodId, $it) {
                                if ($prodId) $q->where('product_id', $prodId);
                                if (!empty($it['sku'])) $q->orWhere('sku', $it['sku']);
                            })->first();
                        $qItemId = $qItem?->id;
                    }

                    $grossPrice = (float)($it['gross_price'] ?? 0);
                    if ($grossPrice <= 0 && $prod) {
                        $grossPrice = (float)($prod->gross_price ?? 0);
                    }

                    SalesOrderItem::create([
                        'order_id' => $so->id,
                        'quotation_item_id' => $qItemId,
                        'product_id' => $prodId,
                        'sku' => $it['sku'] ?? $prod?->sku ?? 'UNKNOWN',
                        'product_name' => $it['product_name'] ?? $it['designation'] ?? $it['description'] ?? ($prod?->designation ?? 'Sản phẩm'),
                        'delivery_date' => $it['delivery_date'] ?? $it['expected_delivery_date'] ?? null,
                        'quantity' => (float)($it['quantity'] ?? 1),
                        'gross_price' => $grossPrice,
                        'discount_percent' => (float)($it['discount_percent'] ?? 0),
                        'selling_price' => (float)($it['selling_price'] ?? $it['unit_price'] ?? 0),
                        'vat_percent' => (float)($it['vat_percent'] ?? 10),
                        'lead_time' => $it['lead_time'] ?? 'Có sẵn',
                        'subtotal' => (float)($it['subtotal'] ?? 0),
                        'status' => $it['status'] ?? 'WIN',
                    ]);
                }
            }

            if (in_array($so->status, ['APPROVED', 'PROCESSING', 'COMPLETED'])) {
                SaleReportService::syncOrderToReport($so);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $so->load(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver']),
                'message' => "Cập nhật đơn bán hàng {$so->order_number} thành công."
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi cập nhật đơn hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function submitApproval($id)
    {
        $so = SalesOrder::where('id', $id)->orWhere('order_number', $id)->first();
        if (!$so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $so->update(['status' => 'PENDING_APPROVAL']);

        return response()->json([
            'success' => true,
            'data' => $so->load(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver']),
            'message' => "Đơn bán hàng {$so->order_number} đã được gửi xét duyệt."
        ]);
    }

    public function approve(Request $request, $id)
    {
        $so = SalesOrder::where('id', $id)->orWhere('order_number', $id)->first();
        if (!$so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $approverId = $this->resolveUserId($request->input('approver_id') ?: $request->input('approved_by'));

        $so->update([
            'status' => 'APPROVED',
            'approved_by' => $approverId,
            'approved_at' => now()->format('d/m/Y H:i'),
        ]);

        SaleReportService::syncOrderToReport($so);

        return response()->json([
            'success' => true,
            'data' => $so->load(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver']),
            'message' => "Đơn bán hàng {$so->order_number} đã được phê duyệt thành công."
        ]);
    }

    public function reject(Request $request, $id)
    {
        $so = SalesOrder::where('id', $id)->orWhere('order_number', $id)->first();
        if (!$so) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $reason = trim($request->input('reason', ''));
        $so->update([
            'status' => 'REJECTED',
            'notes' => $reason ? ($so->notes ? $so->notes . "\n[Lý do từ chối: {$reason}]" : "[Lý do từ chối: {$reason}]") : $so->notes
        ]);

        SaleReportItem::where('order_id', $so->id)->update(['status' => 'CANCELLED']);

        return response()->json([
            'success' => true,
            'data' => $so->load(['items', 'customer', 'contact', 'quotation', 'creator', 'assignee', 'approver']),
            'message' => "Đơn bán hàng {$so->order_number} đã bị từ chối."
        ]);
    }

    public function destroy($id)
    {
        $so = SalesOrder::find($id);
        if (!$so) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }
        SaleReportItem::where('order_id', $so->id)->delete();
        $so->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa đơn bán hàng thành công']);
    }

    private function resolveUserId($userInput)
    {
        if (is_numeric($userInput) && (int)$userInput > 0) {
            return (int)$userInput;
        }

        if (is_string($userInput) && trim($userInput) !== '') {
            $user = User::where('name', trim($userInput))->orWhere('email', trim($userInput))->first();
            if ($user) {
                return $user->id;
            }
        }

        return 6; // Default Super Admin ID
    }
}
