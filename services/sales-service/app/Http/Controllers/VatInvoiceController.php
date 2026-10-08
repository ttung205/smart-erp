<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\VatInvoice;
use App\Models\VatInvoiceItem;
use App\Models\Invoice;
use App\Models\RevenueReportItem;
use App\Services\RevenueReportService;

class VatInvoiceController extends Controller
{
    /**
     * Tự động sinh số hóa đơn tiếp theo: 8 số tăng dần (VD: 00000001, 00000002, 00000123)
     */
    public function getNextInvoiceNumber()
    {
        $lastInvoice = VatInvoice::orderBy('id', 'desc')->first();
        $nextNum = 1;

        if ($lastInvoice && !empty($lastInvoice->invoice_number)) {
            $numOnly = (int)preg_replace('/\D/', '', $lastInvoice->invoice_number);
            if ($numOnly > 0) {
                $nextNum = $numOnly + 1;
            }
        }

        $formatted = str_pad((string)$nextNum, 8, '0', STR_PAD_LEFT);

        return response()->json([
            'success' => true,
            'next_number' => $formatted,
            'series' => '1C26TBB',
        ]);
    }

    /**
     * Lấy danh sách các hóa đơn VAT đã lập
     */
    public function index(Request $request)
    {
        $query = VatInvoice::with(['items', 'customer', 'salesInvoice'])->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhere('invoice_series', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%")
                  ->orWhere('sales_invoice_number', 'like', "%{$s}%")
                  ->orWhere('order_number', 'like', "%{$s}%");
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        $invoices = $query->get();

        return response()->json([
            'success' => true,
            'data' => $invoices,
            'total' => $invoices->count()
        ]);
    }

    /**
     * Lấy danh sách các mặt hàng đã xuất kho nhưng CHƯA xuất hóa đơn VAT (hoặc còn số lượng chưa xuất HĐ)
     * Dùng cho Tab / Dialog chọn mặt hàng lập hóa đơn VAT
     */
    public function getUnbilledItems(Request $request)
    {
        $query = RevenueReportItem::with(['invoice', 'customer', 'product'])
            ->where('account_code', '1312')
            ->where('remaining_invoice_quantity', '>', 0)
            ->orderBy('id', 'desc');

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->input('invoice_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhere('order_number', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%")
                  ->orWhere('product_name', 'like', "%{$s}%");
            });
        }

        $items = $query->get();

        return response()->json([
            'success' => true,
            'data' => $items,
            'total' => $items->count()
        ]);
    }

    /**
     * Lập hóa đơn VAT mới (cho phép xuất toàn bộ hoặc 1 phần các mặt hàng đã xuất kho)
     */
    public function store(Request $request)
    {
        $request->validate([
            'invoice_number' => 'required|string|max:50',
            'invoice_date' => 'required|string',
            'items' => 'required|array|min:1',
        ]);

        try {
            DB::beginTransaction();

            $salesInvoiceId = $request->input('sales_invoice_id');
            $salesInvoice = null;
            if ($salesInvoiceId) {
                $salesInvoice = Invoice::with(['deliveryRequest.order.customer'])->find($salesInvoiceId);
            }

            $customer = $salesInvoice?->deliveryRequest?->order?->customer;
            $customerName = $request->input('customer_name') ?: ($customer?->name ?: $salesInvoice?->customer_name);
            $customerTaxCode = $request->input('customer_tax_code') ?: ($customer?->tax_code ?: '');
            $customerAddress = $request->input('customer_address') ?: ($customer?->address ?: $salesInvoice?->shipping_address);
            $orderNumber = $salesInvoice?->deliveryRequest?->customer_order_number ?: ($salesInvoice?->deliveryRequest?->order_number ?: '');

            $vatInvoice = VatInvoice::create([
                'invoice_number' => trim($request->input('invoice_number')),
                'invoice_series' => trim($request->input('invoice_series', '1C26TBB')),
                'invoice_date' => $request->input('invoice_date', now()->format('Y-m-d')),
                'sales_invoice_id' => $salesInvoiceId,
                'sales_invoice_number' => $salesInvoice?->invoice_number,
                'order_id' => $salesInvoice?->deliveryRequest?->order_id,
                'order_number' => $orderNumber,
                'customer_id' => $request->input('customer_id') ?: $customer?->id,
                'customer_name' => $customerName,
                'customer_tax_code' => $customerTaxCode,
                'customer_address' => $customerAddress,
                'currency' => $request->input('currency') ?: ($salesInvoice?->currency ?: 'VND'),
                'exchange_rate' => (float)($request->input('exchange_rate') ?: ($salesInvoice?->exchange_rate ?: 1.0)),
                'created_by' => $request->input('created_by') ?: 'Kế toán viên',
                'status' => 'ISSUED',
                'notes' => $request->input('notes'),
                'subtotal' => 0,
                'vat_total' => 0,
                'total_amount' => 0,
            ]);

            $calcSubtotal = 0;
            $calcVatTotal = 0;
            $createdItems = [];

            foreach ($request->input('items', []) as $it) {
                $qty = (float)($it['quantity'] ?? 1);
                if ($qty <= 0) continue;

                $price = (float)($it['unit_price'] ?? $it['selling_price'] ?? 0);
                $lineSub = (float)($it['subtotal'] ?? ($qty * $price));
                $vatPercent = (float)($it['vat_percent'] ?? $it['vat_rate'] ?? 10);
                $lineVat = round($lineSub * ($vatPercent / 100), 2);
                $lineTotal = $lineSub + $lineVat;

                $created = VatInvoiceItem::create([
                    'vat_invoice_id' => $vatInvoice->id,
                    'sales_invoice_item_id' => $it['sales_invoice_item_id'] ?? $it['invoice_item_id'] ?? null,
                    'order_item_id' => $it['order_item_id'] ?? null,
                    'product_id' => $it['product_id'] ?? null,
                    'sku' => $it['sku'] ?? '',
                    'product_name' => $it['product_name'] ?? ($it['description'] ?? 'Sản phẩm'),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $lineSub,
                    'vat_percent' => $vatPercent,
                    'vat_amount' => $lineVat,
                    'total_amount' => $lineTotal,
                ]);

                $createdItems[] = $created;
                $calcSubtotal += $lineSub;
                $calcVatTotal += $lineVat;
            }

            $vatInvoice->update([
                'subtotal' => $calcSubtotal,
                'vat_total' => $calcVatTotal,
                'total_amount' => $calcSubtotal + $calcVatTotal,
            ]);

            // Cập nhật chuyển trạng thái doanh thu từ TK 1312 sang TK 1311
            RevenueReportService::processVatInvoiceIssued($vatInvoice, $createdItems);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $vatInvoice->load('items', 'customer'),
                'message' => "Lập hóa đơn VAT {$vatInvoice->invoice_number} thành công và đã ghi nhận doanh thu TK 1311."
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi lập hóa đơn VAT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem chi tiết hóa đơn VAT
     */
    public function show($id)
    {
        $inv = VatInvoice::with(['items', 'customer', 'salesInvoice'])->find($id);
        if (!$inv) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy hóa đơn VAT'], 404);
        }

        return response()->json(['success' => true, 'data' => $inv]);
    }
}
