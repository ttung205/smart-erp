<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVatInvoiceRequest;
use App\Models\Invoice;
use App\Models\RevenueReportItem;
use App\Models\VatInvoice;
use App\Models\VatInvoiceItem;
use App\Services\CustomerReceivableService;
use App\Services\RevenueReportService;
use App\Support\VatInvoiceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class VatInvoiceController extends Controller
{
    public function syncFinance(Request $request, CustomerReceivableService $service): JsonResponse
    {
        $synced = 0;
        $failed = [];
        VatInvoice::query()->where('status', 'ISSUED')->whereNull('adjustment_id')->whereNull('finance_synced_at')->with('salesInvoice')->orderBy('id')
            ->chunkById(100, function ($invoices) use ($request, $service, &$synced, &$failed): void {
                foreach ($invoices as $invoice) {
                    try {
                        $service->createForVatInvoice($invoice, $invoice->salesInvoice, $request->bearerToken());
                        $invoice->update(['finance_synced_at' => now()]);
                        $synced++;
                    } catch (\Throwable $exception) {
                        report($exception);
                        $failed[] = $invoice->invoice_number;
                    }
                }
            });

        return response()->json(['success' => count($failed) === 0, 'synced' => $synced, 'failed' => $failed],
            count($failed) === 0 ? 200 : 207);
    }

    /**
     * Tự động sinh số hóa đơn tiếp theo: 8 số tăng dần (VD: 00000001, 00000002, 00000123)
     */
    public function getNextInvoiceNumber(Request $request)
    {
        $series = $request->input('series', '1C'.now()->format('y').'TBB');
        $formatted = VatInvoiceNumberGenerator::preview($series);

        return response()->json([
            'success' => true,
            'next_number' => $formatted,
            'series' => $series,
        ]);
    }

    /**
     * Lấy danh sách các hóa đơn VAT đã lập
     */
    public function index(Request $request)
    {
        $query = VatInvoice::with(['items', 'salesInvoice', 'adjustment.originalInvoice'])->where('status', 'ISSUED')->orderBy('id', 'desc');

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

        $invoices = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json([
            'success' => true,
            'data' => $invoices->items(),
            'total' => $invoices->total(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
            ],
        ]);
    }

    /**
     * Lấy danh sách các mặt hàng đã xuất kho nhưng CHƯA xuất hóa đơn VAT (hoặc còn số lượng chưa xuất HĐ)
     * Dùng cho Tab / Dialog chọn mặt hàng lập hóa đơn VAT
     */
    public function getUnbilledItems(Request $request)
    {
        $query = RevenueReportItem::with(['invoice', 'order'])
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

        $items = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'total' => $items->total(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
            ],
        ]);
    }

    /**
     * Lập hóa đơn VAT mới (cho phép xuất toàn bộ hoặc 1 phần các mặt hàng đã xuất kho)
     */
    public function store(StoreVatInvoiceRequest $request, CustomerReceivableService $receivableService): JsonResponse
    {
        try {
            DB::beginTransaction();

            $salesInvoiceId = $request->input('sales_invoice_id');
            $salesInvoice = null;
            if ($salesInvoiceId) {
                $salesInvoice = Invoice::with(['deliveryRequest.order'])->find($salesInvoiceId);
            }

            // Thông tin khách hàng lấy từ snapshot trên đơn bán hàng
            $order = $salesInvoice?->deliveryRequest?->order;
            $customerName = $request->input('customer_name') ?: ($order?->customer_name ?: $salesInvoice?->customer_name);
            $customerTaxCode = $request->input('customer_tax_code') ?: ($order?->customer_tax_code ?: '');
            $customerAddress = $request->input('customer_address') ?: ($order?->customer_address ?: $salesInvoice?->shipping_address);
            $orderNumber = $salesInvoice?->deliveryRequest?->customer_order_number ?: ($salesInvoice?->deliveryRequest?->order_number ?: '');

            $series = trim($request->input('invoice_series', '1C'.now()->format('y').'TBB'));
            $invoiceNumber = VatInvoiceNumberGenerator::next($series);
            $vatInvoice = VatInvoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_series' => $series,
                'invoice_date' => $request->input('invoice_date', now()->format('Y-m-d')),
                'sales_invoice_id' => $salesInvoiceId,
                'sales_invoice_number' => $salesInvoice?->invoice_number,
                'order_id' => $salesInvoice?->deliveryRequest?->order_id,
                'order_number' => $orderNumber,
                'customer_id' => $request->input('customer_id') ?: $order?->customer_id,
                'customer_name' => $customerName,
                'customer_tax_code' => $customerTaxCode,
                'customer_address' => $customerAddress,
                'currency' => $request->input('currency') ?: ($salesInvoice?->currency ?: 'VND'),
                'exchange_rate' => (float) ($request->input('exchange_rate') ?: ($salesInvoice?->exchange_rate ?: 1.0)),
                'created_by' => $request->attributes->get('actor')->name,
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
                $qty = (float) ($it['quantity'] ?? 1);
                $reportItem = RevenueReportItem::whereKey($it['revenue_report_item_id'])->lockForUpdate()->firstOrFail();
                if ($reportItem->account_code !== '1312'
                    || (int) $reportItem->invoice_id !== (int) $salesInvoiceId
                    || (int) $reportItem->invoice_item_id !== (int) $it['sales_invoice_item_id']) {
                    abort(422, 'Dòng doanh thu không thuộc chứng từ bán hàng đã chọn hoặc đã được xuất hóa đơn.');
                }
                if ($qty > (float) $reportItem->remaining_invoice_quantity) {
                    abort(422, "Số lượng xuất hóa đơn của SKU {$reportItem->sku} vượt quá số lượng còn lại.");
                }

                $price = (float) $reportItem->unit_price;
                $lineSub = $qty * $price;
                $vatPercent = (float) $reportItem->vat_rate;
                $lineVat = round($lineSub * ($vatPercent / 100), 2);
                $lineTotal = $lineSub + $lineVat;

                $created = VatInvoiceItem::create([
                    'vat_invoice_id' => $vatInvoice->id,
                    'sales_invoice_item_id' => $it['sales_invoice_item_id'] ?? $it['invoice_item_id'] ?? null,
                    'revenue_report_item_id' => $reportItem->id,
                    'order_item_id' => $it['order_item_id'] ?? null,
                    'product_id' => $reportItem->product_id,
                    'sku' => $reportItem->sku,
                    'product_name' => $reportItem->product_name,
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

            $financeSynced = true;
            try {
                $receivableService->createForVatInvoice($vatInvoice->fresh(), $salesInvoice, $request->bearerToken());
                $vatInvoice->update(['finance_synced_at' => now()]);
            } catch (\Throwable $syncError) {
                report($syncError);
                $financeSynced = false;
            }

            return response()->json([
                'success' => true,
                'finance_synced' => $financeSynced,
                'data' => $vatInvoice->load('items'),
                'message' => $financeSynced
                    ? "Lập hóa đơn VAT {$vatInvoice->invoice_number} thành công và đã ghi nhận công nợ."
                    : "Đã lập hóa đơn VAT {$vatInvoice->invoice_number}, nhưng chưa đồng bộ công nợ. Hãy dùng nút đồng bộ trên Sổ cái.",
            ], 201);
        } catch (HttpExceptionInterface $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Không thể lập hóa đơn VAT.',
            ], 500);
        }
    }

    /**
     * Xem chi tiết hóa đơn VAT
     */
    public function show($id)
    {
        $inv = VatInvoice::with(['items', 'salesInvoice', 'adjustment.originalInvoice'])->find($id);
        if (! $inv) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy hóa đơn VAT'], 404);
        }

        return response()->json(['success' => true, 'data' => $inv]);
    }
}
