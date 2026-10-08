<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleReportItem;

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
            $query->where('order_number', 'like', '%' . $request->input('order_number') . '%');
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('sku')) {
            $query->where('sku', 'like', '%' . $request->input('sku') . '%');
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

        $items = $query->get();

        $summary = [
            'total_items' => $items->count(),
            'total_quantity' => $items->sum('quantity'),
            'total_delivered' => $items->sum('delivered_quantity'),
            'total_remaining' => $items->sum('remaining_quantity'),
            'total_amount' => $items->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $items,
            'summary' => $summary,
        ]);
    }

    /**
     * Lấy chi tiết 1 dòng báo cáo
     */
    public function show($id)
    {
        $item = SaleReportItem::with(['order', 'product', 'customer', 'salesperson'])->find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy dòng báo cáo'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }
}
