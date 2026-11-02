<?php

namespace App\Http\Controllers;

use App\Models\SaleReportItem;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SaleReportController extends Controller
{
    /**
     * Lấy danh sách báo cáo mặt hàng bán (sale_report_items)
     */
    #[OA\Get(
        path: '/reports/sale-items',
        summary: 'Báo cáo dòng hàng bán',
        description: 'Yêu cầu quyền order.view. Mỗi dòng là một mặt hàng trên đơn bán, kèm số lượng đã giao và còn lại.',
        tags: ['Báo cáo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'order_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'order_number', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'product_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sku', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'customer_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'salesperson_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Các dòng báo cáo và phân trang'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.view'),
        ]
    )]
    #[OA\Get(
        path: '/sale-report-items',
        summary: 'Báo cáo dòng hàng bán (đường dẫn cũ)',
        description: 'Giống /reports/sale-items, giữ lại cho client cũ.',
        tags: ['Báo cáo'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Các dòng báo cáo và phân trang'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.view'),
        ]
    )]
    public function index(Request $request)
    {
        $query = SaleReportItem::with(['order'])->orderBy('id', 'desc');

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
                    // Tên khách hàng / người phụ trách: tìm theo snapshot trên đơn hàng
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%")
                            ->orWhere('created_by_name', 'like', "%{$search}%")
                            ->orWhere('assigned_to_name', 'like', "%{$search}%");
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
    #[OA\Get(
        path: '/reports/sale-items/{id}',
        summary: 'Chi tiết một dòng báo cáo bán hàng',
        description: 'Yêu cầu quyền order.view.',
        tags: ['Báo cáo'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Dòng báo cáo'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền order.view'),
            new OA\Response(response: 404, description: 'Không tìm thấy dòng báo cáo'),
        ]
    )]
    #[OA\Get(
        path: '/sale-report-items/{id}',
        summary: 'Chi tiết một dòng báo cáo bán hàng (đường dẫn cũ)',
        description: 'Giống /reports/sale-items/{id}.',
        tags: ['Báo cáo'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Dòng báo cáo'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 404, description: 'Không tìm thấy dòng báo cáo'),
        ]
    )]
    public function show($id)
    {
        $item = SaleReportItem::with(['order'])->find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy dòng báo cáo'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }
}
