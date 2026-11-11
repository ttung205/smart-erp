<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Xuất dữ liệu danh mục cho kho phân tích của ai-service (chỉ service nội bộ gọi, khóa X-Internal-Service-Key).
 * Phân trang theo khóa chính: ?after_id=0&limit=500, trả về next_after_id = null khi hết dữ liệu.
 */
class InternalExportController extends Controller
{
    private const DEFAULT_LIMIT = 500;

    private const MAX_LIMIT = 1000;

    public function products(Request $request): JsonResponse
    {
        $query = DB::table('products as p')
            ->leftJoin('categories as cg', 'cg.id', '=', 'p.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->select([
                'p.id', 'p.sku', 'p.designation', 'p.description', 'cg.name as category', 'b.name as brand',
                'p.com', 'p.unit', 'p.gross_price', 'p.cost_price', 'p.min_stock', 'p.status', 'p.created_at',
            ]);

        return $this->page($request, $query, 'p.id');
    }

    public function customers(Request $request): JsonResponse
    {
        $query = DB::table('customers')->select([
            'id', 'code', 'name', 'customer_type', 'address', 'tax_code', 'phone', 'email',
            'credit_limit', 'status', 'created_at',
        ]);

        return $this->page($request, $query, 'id');
    }

    public function suppliers(Request $request): JsonResponse
    {
        $query = DB::table('suppliers')->select([
            'id', 'code', 'name', 'supplier_type', 'supplier_group', 'payment_terms', 'email', 'phone',
            'contact_person', 'status', 'notes',
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

        $last = end($items);
        $nextAfterId = count($items) === $limit && $last ? (int) $last['id'] : null;

        return response()->json(['success' => true, 'data' => ['items' => $items, 'next_after_id' => $nextAfterId]]);
    }
}
