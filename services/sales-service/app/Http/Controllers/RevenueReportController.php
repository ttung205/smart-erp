<?php

namespace App\Http\Controllers;

use App\Models\RevenueReportItem;
use Illuminate\Http\Request;

class RevenueReportController extends Controller
{
    /**
     * Lấy danh sách báo cáo doanh thu mặt hàng (TK 1311 & TK 1312)
     */
    public function index(Request $request)
    {
        $query = RevenueReportItem::with(['invoice', 'vatInvoice', 'product', 'customer', 'salesperson'])->orderBy('id', 'desc');

        if ($request->filled('account_code')) {
            $query->where('account_code', $request->input('account_code'));
        }

        if ($request->filled('is_vat_invoiced')) {
            $query->where('is_vat_invoiced', $request->boolean('is_vat_invoiced'));
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->input('invoice_id'));
        }

        if ($request->filled('vat_invoice_id')) {
            $query->where('vat_invoice_id', $request->input('vat_invoice_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('salesperson_id')) {
            $query->where('salesperson_id', $request->input('salesperson_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('sku')) {
            $query->where('sku', 'like', '%'.$request->input('sku').'%');
        }

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                    ->orWhere('vat_invoice_number', 'like', "%{$s}%")
                    ->orWhere('order_number', 'like', "%{$s}%")
                    ->orWhere('sku', 'like', "%{$s}%")
                    ->orWhere('product_name', 'like', "%{$s}%")
                    ->orWhereHas('customer', function ($cq) use ($s) {
                        $cq->where('name', 'like', "%{$s}%");
                    });
            });
        }

        $summaryQuery = clone $query;
        $summaryRow = $summaryQuery->toBase()->selectRaw("
            COUNT(*) as total_items,
            COALESCE(SUM(quantity), 0) as total_quantity,
            COALESCE(SUM(subtotal), 0) as total_subtotal,
            COALESCE(SUM(vat_amount), 0) as total_vat,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN account_code = '1311' THEN total_amount ELSE 0 END), 0) as revenue_1311,
            COALESCE(SUM(CASE WHEN account_code = '1312' THEN total_amount ELSE 0 END), 0) as revenue_1312
        ")->first();
        $items = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));

        $summary = [
            'total_items' => (int) $summaryRow->total_items,
            'total_quantity' => (float) $summaryRow->total_quantity,
            'total_subtotal' => (float) $summaryRow->total_subtotal,
            'total_vat' => (float) $summaryRow->total_vat,
            'total_revenue' => (float) $summaryRow->total_revenue,
            'revenue_1311' => (float) $summaryRow->revenue_1311,
            'revenue_1312' => (float) $summaryRow->revenue_1312,
        ];

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'summary' => $summary,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Lấy chi tiết 1 dòng báo cáo doanh thu
     */
    public function show($id)
    {
        $item = RevenueReportItem::with(['invoice', 'vatInvoice', 'product', 'customer', 'salesperson'])->find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy dòng báo cáo doanh thu'], 404);
        }

        return response()->json(['success' => true, 'data' => $item]);
    }
}
