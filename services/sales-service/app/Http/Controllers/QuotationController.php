<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;

class QuotationController extends Controller
{
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

        $quotations = $query->get()->map(function ($q) {
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
                'subtotal' => (float)$q->subtotal,
                'vat_total' => (float)$q->vat_total,
                'total_amount' => (float)$q->total_amount,
                'items_count' => $q->items->count(),
                'items' => $q->items,
            ];
        });

        return response()->json(['success' => true, 'data' => $quotations]);
    }

    public function show($id)
    {
        $q = Quotation::with('items')
            ->where('id', $id)
            ->orWhere('quotation_number', $id)
            ->first();

        if (!$q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy báo giá']], 404);
        }

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function store(Request $request)
    {
        $customerName = trim($request->input('customer_name', ''));
        if (empty($customerName)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Khách hàng không được để trống']], 422);
        }

        DB::beginTransaction();
        try {
            $customerCode = trim($request->input('customer_code', 'KH01'));
            $date = now();
            $d = $date->format('d');
            $m = $date->format('m');
            $y = $date->format('y');
            $dateStr = "{$d}{$m}{$y}";
            $baseNumber = "QT-{$customerCode}-{$dateStr}";

            // Quy định số báo giá: QT-mã khách hàng-ddmmyy
            // Nếu có nhiều báo giá cùng 1 khách trong 1 ngày: thêm -A, -B, -C...
            if (!Quotation::where('quotation_number', $baseNumber)->exists()) {
                $qNumber = $baseNumber;
            } else {
                $letters = range('A', 'Z');
                $letterIdx = 0;
                do {
                    $letterSuffix = $letters[$letterIdx] ?? ('Z' . ($letterIdx - 25));
                    $qNumber = "{$baseNumber}-{$letterSuffix}";
                    $letterIdx++;
                } while (Quotation::where('quotation_number', $qNumber)->exists());
            }

            // Nếu người dùng chủ động truyền số báo giá tùy chỉnh hợp lệ (khác auto placeholder)
            $customNumber = trim($request->input('quotation_number', ''));
            if ($customNumber && !str_starts_with($customNumber, '(Tự động') && !Quotation::where('quotation_number', $customNumber)->exists()) {
                $qNumber = $customNumber;
            }

            $quotation = Quotation::create([
                'quotation_number' => $qNumber,
                'customer_id' => $request->input('customer_id'),
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'contact_id' => $request->input('contact_id'),
                'contact_name' => $request->input('contact_name'),
                'quotation_date' => $request->input('quotation_date', now()->format('d/m/Y')),
                'currency' => $request->input('currency', 'VND'),
                'exchange_rate' => (float)$request->input('exchange_rate', 1.0),
                'created_by' => $request->input('created_by', 'admin'),
                'assigned_to' => $request->input('assigned_to', 'admin'),
                'status' => 'DRAFT',
                'subtotal' => (float)$request->input('subtotal', 0),
                'vat_total' => (float)$request->input('vat_total', 0),
                'total_amount' => (float)$request->input('total_amount', 0),
                'notes' => $request->input('notes'),
            ]);

            $items = $request->input('items', []);
            foreach ($items as $it) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'sku' => $it['sku'] ?? 'UNKNOWN',
                    'designation' => $it['designation'] ?? null,
                    'brand' => $it['brand'] ?? null,
                    'description' => $it['description'] ?? null,
                    'com' => $it['com'] ?? 'Việt Nam',
                    'unit' => $it['unit'] ?? 'Cái',
                    'quantity' => (float)($it['quantity'] ?? 1),
                    'gross_price' => (float)($it['gross_price'] ?? 0),
                    'discount_percent' => (float)($it['discount_percent'] ?? 0),
                    'selling_price' => (float)($it['selling_price'] ?? 0),
                    'vat_percent' => (float)($it['vat_percent'] ?? 10),
                    'lead_time' => $it['lead_time'] ?? 'Có sẵn',
                    'subtotal' => (float)($it['subtotal'] ?? 0),
                    'is_won' => ($it['status'] ?? '') === 'WIN' || (bool)($it['won'] ?? $it['is_won'] ?? false),
                    'won_quantity' => (float)($it['won_quantity'] ?? 0),
                    'status' => $it['status'] ?? (($it['won'] ?? false) ? 'WIN' : 'PENDING'),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $quotation->load('items'),
                'message' => "Tạo báo giá {$quotation->quotation_number} thành công."
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => ['code' => 'STORE_FAILED', 'message' => $e->getMessage()]], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $q = Quotation::where('id', $id)->orWhere('quotation_number', $id)->first();
        if (!$q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy báo giá']], 404);
        }

        DB::beginTransaction();
        try {
            $q->update([
                'customer_id' => $request->input('customer_id', $q->customer_id),
                'customer_code' => $request->input('customer_code', $q->customer_code),
                'customer_name' => $request->input('customer_name', $q->customer_name),
                'contact_id' => $request->input('contact_id', $q->contact_id),
                'contact_name' => $request->input('contact_name', $q->contact_name),
                'currency' => $request->input('currency', $q->currency),
                'exchange_rate' => (float)$request->input('exchange_rate', $q->exchange_rate),
                'assigned_to' => $request->input('assigned_to', $q->assigned_to),
                'subtotal' => (float)$request->input('subtotal', $q->subtotal),
                'vat_total' => (float)$request->input('vat_total', $q->vat_total),
                'total_amount' => (float)$request->input('total_amount', $q->total_amount),
                'notes' => $request->input('notes', $q->notes),
            ]);

            if ($request->has('items')) {
                QuotationItem::where('quotation_id', $q->id)->delete();
                foreach ($request->input('items', []) as $it) {
                    QuotationItem::create([
                        'quotation_id' => $q->id,
                        'sku' => $it['sku'] ?? 'UNKNOWN',
                        'designation' => $it['designation'] ?? null,
                        'brand' => $it['brand'] ?? null,
                        'description' => $it['description'] ?? null,
                        'com' => $it['com'] ?? 'Việt Nam',
                        'unit' => $it['unit'] ?? 'Cái',
                        'quantity' => (float)($it['quantity'] ?? 1),
                        'gross_price' => (float)($it['gross_price'] ?? 0),
                        'discount_percent' => (float)($it['discount_percent'] ?? 0),
                        'selling_price' => (float)($it['selling_price'] ?? 0),
                        'vat_percent' => (float)($it['vat_percent'] ?? 10),
                        'lead_time' => $it['lead_time'] ?? 'Có sẵn',
                        'subtotal' => (float)($it['subtotal'] ?? 0),
                        'is_won' => ($it['status'] ?? '') === 'WIN' || (bool)($it['won'] ?? $it['is_won'] ?? false),
                        'won_quantity' => (float)($it['won_quantity'] ?? 0),
                        'status' => $it['status'] ?? (($it['won'] ?? false) ? 'WIN' : 'PENDING'),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $q->load('items'),
                'message' => "Cập nhật báo giá {$q->quotation_number} thành công."
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => ['code' => 'UPDATE_FAILED', 'message' => $e->getMessage()]], 500);
        }
    }

    public function submitApproval($id)
    {
        $q = Quotation::where('id', $id)->orWhere('quotation_number', $id)->first();
        if (!$q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $q->update(['status' => 'PENDING_APPROVAL']);

        return response()->json([
            'success' => true,
            'data' => $q,
            'message' => "Báo giá {$q->quotation_number} đã được gửi xét duyệt."
        ]);
    }

    public function approve(Request $request, $id)
    {
        $q = Quotation::where('id', $id)->orWhere('quotation_number', $id)->first();
        if (!$q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $approverName = $request->input('approver_name', 'admin');
        $q->update([
            'status' => 'APPROVED',
            'approved_by' => $approverName,
            'approved_at' => now()->format('d/m/Y H:i'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $q,
            'message' => "Báo giá {$q->quotation_number} đã được phê duyệt thành công."
        ]);
    }

    public function reject(Request $request, $id)
    {
        $q = Quotation::where('id', $id)->orWhere('quotation_number', $id)->first();
        if (!$q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $reason = trim($request->input('reason', ''));
        $q->update([
            'status' => 'REJECTED',
            'notes' => $reason ? ($q->notes ? $q->notes . "\n[Lý do từ chối: {$reason}]" : "[Lý do từ chối: {$reason}]") : $q->notes
        ]);

        return response()->json([
            'success' => true,
            'data' => $q,
            'message' => "Báo giá {$q->quotation_number} đã bị từ chối."
        ]);
    }

    public function createSO(Request $request, $id)
    {
        $q = Quotation::with('items')->where('id', $id)->orWhere('quotation_number', $id)->first();
        if (!$q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        DB::beginTransaction();
        try {
            $randNum = rand(1000, 9999);
            $soNumber = "SO-2026-{$randNum}";

            $so = SalesOrder::create([
                'order_number' => $soNumber,
                'customer_order_number' => $soNumber,
                'quotation_id' => $q->id,
                'customer_id' => $q->customer_id,
                'contact_id' => $q->contact_id,
                'order_date' => now()->format('d/m/Y'),
                'currency' => $q->currency ?? 'VND',
                'exchange_rate' => (float)($q->exchange_rate ?? 1.0),
                'created_by' => is_numeric($q->created_by) ? (int)$q->created_by : 6,
                'assigned_to' => is_numeric($q->assigned_to) ? (int)$q->assigned_to : 6,
                'subtotal' => (float)$request->input('subtotal', $q->subtotal),
                'vat_total' => (float)$request->input('vat_total', $q->vat_total),
                'total_amount' => (float)$request->input('total_amount', $q->total_amount),
                'status' => 'DRAFT',
                'notes' => "Sinh tự động từ Báo Giá {$q->quotation_number}",
            ]);

            $wonItems = $request->input('items', []);
            if (empty($wonItems)) {
                $wonItems = $q->items->where('is_won', true);
            }

            foreach ($wonItems as $it) {
                SalesOrderItem::create([
                    'order_id' => $so->id,
                    'sku' => $it['sku'] ?? $it['product_sku'] ?? '',
                    'product_name' => $it['designation'] ?? $it['description'] ?? $it['product_name'] ?? '',
                    'quantity' => (float)($it['won_quantity'] ?? $it['quantity'] ?? 1),
                    'unit_price' => (float)($it['selling_price'] ?? $it['unit_price'] ?? 0),
                    'subtotal' => (float)($it['subtotal'] ?? $it['amount'] ?? 0),
                ]);
            }

            $q->update(['status' => 'CONVERTED']);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $so->load('items'),
                'message' => "Đã sinh Đơn Bán Hàng {$so->order_number} thành công từ Item Win."
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => ['code' => 'SO_FAILED', 'message' => $e->getMessage()]], 500);
        }
    }

    public function destroy($id)
    {
        $q = Quotation::where('id', $id)->orWhere('quotation_number', $id)->first();
        if (!$q) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }

        $q->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa báo giá thành công.']);
    }
}
