<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RevenueReportItem;

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
            $query->where('sku', 'like', '%' . $request->input('sku') . '%');
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

        $items = $query->get();

        $summary = [
            'total_items' => $items->count(),
            'total_quantity' => $items->sum('quantity'),
            'total_subtotal' => $items->sum('subtotal'),
            'total_vat' => $items->sum('vat_amount'),
            'total_revenue' => $items->sum('total_amount'),
            'revenue_1311' => $items->where('account_code', '1311')->sum('total_amount'),
            'revenue_1312' => $items->where('account_code', '1312')->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $items,
            'summary' => $summary,
        ]);
    }

    /**
     * Lấy chi tiết 1 dòng báo cáo doanh thu
     */
    public function show($id)
    {
        $item = RevenueReportItem::with(['invoice', 'vatInvoice', 'product', 'customer', 'salesperson'])->find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy dòng báo cáo doanh thu'], 404);
        }

        return response()->json(['success' => true, 'data' => $item]);
    }
}
