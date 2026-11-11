<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Xuất dữ liệu bán hàng cho kho phân tích của ai-service (chỉ service nội bộ, X-Internal-Service-Key).
 * Phân trang theo khóa chính: ?after_id=0&limit=500, next_after_id = null khi hết dữ liệu.
 */
class InternalExportController extends Controller
{
    private const DEFAULT_LIMIT = 500;

    private const MAX_LIMIT = 1000;

    /** Dòng doanh thu đã ghi sổ (nguồn của fact_sales trong kho phân tích). */
    public function revenueItems(Request $request): JsonResponse
    {
        $query = DB::table('revenue_report_items')->select([
            'id', 'issue_date', 'invoice_number', 'order_id', 'order_number', 'customer_id', 'product_id',
            'quantity', 'unit_price', 'subtotal', 'vat_amount', 'salesperson_id', 'is_vat_invoiced',
        ]);

        return $this->page($request, $query, 'id');
    }

    /** Đơn bán. `salesperson` = người phụ trách (assigned_to, không có thì lấy người tạo). */
    public function orders(Request $request): JsonResponse
    {
        $query = DB::table('sales_orders')->select([
            'id', 'order_number', 'order_date', 'customer_id', 'status', 'subtotal', 'total_amount',
            DB::raw('COALESCE(assigned_to, created_by) as salesperson'), 'quotation_id',
        ]);

        return $this->page($request, $query, 'id');
    }

    /** Dòng hàng của đơn bán. */
    public function orderLines(Request $request): JsonResponse
    {
        $query = DB::table('sales_order_items')->select([
            'id', 'order_id', 'product_id', 'quantity', 'delivered_quantity', 'selling_price',
            'discount_percent', 'subtotal',
        ]);

        return $this->page($request, $query, 'id');
    }

    /** @param  Builder  $query  câu truy vấn đã chọn cột; $idColumn là cột khóa để phân trang */
    private function page(Request $request, Builder $query, string $idColumn): JsonResponse
    {
        $afterId = max(0, (int) $request->query('after_id', 0));
        $limit = min(self::MAX_LIMIT, max(1, (int) $request->query('limit', self::DEFAULT_LIMIT)));

        $items = $query->where($idColumn, '>', $afterId)
            ->orderBy($idColumn)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();

        $nextAfterId = count($items) === $limit ? (int) $items[count($items) - 1]['id'] : null;

        return response()->json(['success' => true, 'data' => ['items' => $items, 'next_after_id' => $nextAfterId]]);
    }
}
