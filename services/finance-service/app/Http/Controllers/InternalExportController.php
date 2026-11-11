<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Xuất công nợ phải thu cho kho phân tích của ai-service (chỉ service nội bộ, X-Internal-Service-Key).
 * Phân trang theo khóa chính: ?after_id=0&limit=500, next_after_id = null khi hết dữ liệu.
 */
class InternalExportController extends Controller
{
    private const DEFAULT_LIMIT = 500;

    private const MAX_LIMIT = 1000;

    public function receivables(Request $request): JsonResponse
    {
        $afterId = max(0, (int) $request->query('after_id', 0));
        $limit = min(self::MAX_LIMIT, max(1, (int) $request->query('limit', self::DEFAULT_LIMIT)));

        $items = DB::table('customer_receivables')
            ->select([
                'id', 'customer_id', 'invoice_number', 'invoice_date', 'due_date',
                'original_amount', 'paid_amount', 'outstanding_amount', 'status',
            ])
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();

        $nextAfterId = count($items) === $limit ? (int) $items[count($items) - 1]['id'] : null;

        return response()->json(['success' => true, 'data' => ['items' => $items, 'next_after_id' => $nextAfterId]]);
    }
}
