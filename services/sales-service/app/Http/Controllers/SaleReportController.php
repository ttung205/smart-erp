<?php

namespace App\Http\Controllers;

use App\Models\SaleReportItem;
use Illuminate\Http\Request;

class SaleReportController extends Controller
{
    /**
     * Lấy danh sách báo cáo mặt hàng bán (sale_report_items)
     */
    public function index(Request $request)
    {
        $query = SaleReportItem::with(['order', 'product', 'customer', 'salesperson'])->orderBy('id', 'desc');

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->input('order_id'));
        }

        if ($request->filled('order_number')) {
            $query->where('order_number', 'like', '%'.$request->input('order_number').'%');
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('sku')) {
            $query->where('sku', 'like', '%'.$request->input('sku').'%');
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('salesperson_id')) {
            $query->where('salesperson_id', $request->input('salesperson_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('salesperson', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $summaryQuery = clone $query;
        $summaryRow = $summaryQuery->toBase()->selectRaw('
            COUNT(*) as total_items,
            COALESCE(SUM(quantity), 0) as total_quantity,
            COALESCE(SUM(delivered_quantity), 0) as total_delivered,
            COALESCE(SUM(remaining_quantity), 0) as total_remaining,
            COALESCE(SUM(total_amount), 0) as total_amount
        ')->first();
        $items = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));

        $summary = [
            'total_items' => (int) $summaryRow->total_items,
            'total_quantity' => (float) $summaryRow->total_quantity,
            'total_delivered' => (float) $summaryRow->total_delivered,
            'total_remaining' => (float) $summaryRow->total_remaining,
            'total_amount' => (float) $summaryRow->total_amount,
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
     * Lấy chi tiết 1 dòng báo cáo
     */
    public function show($id)
    {
        $item = SaleReportItem::with(['order', 'product', 'customer', 'salesperson'])->find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy dòng báo cáo'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }
}
