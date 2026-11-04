<?php

namespace App\Http\Controllers;

use App\Models\RevenueReportItem;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RevenueReportController extends Controller
{
    /**
     * Lấy danh sách báo cáo doanh thu mặt hàng (TK 1311 & TK 1312)
     */
    #[OA\Get(
        path: '/reports/revenue-items',
        summary: 'Báo cáo doanh thu (TK 1311 và 1312)',
        description: 'Yêu cầu quyền finance.view hoặc invoice.view. Mỗi dòng là doanh thu một mặt hàng khi xuất kho, kèm trạng thái đã xuất hóa đơn GTGT.',
        tags: ['Báo cáo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'account_code', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['1311', '1312'])),
            new OA\Parameter(name: 'is_vat_invoiced', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'invoice_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'vat_invoice_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'customer_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'salesperson_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'product_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sku', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Các dòng doanh thu và phân trang'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.view hoặc invoice.view'),
        ]
    )]
    #[OA\Get(
        path: '/revenue-report-items',
        summary: 'Báo cáo doanh thu (đường dẫn cũ)',
        description: 'Giống /reports/revenue-items, giữ lại cho client cũ.',
        tags: ['Báo cáo'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Các dòng doanh thu và phân trang'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.view hoặc invoice.view'),
        ]
    )]
    public function index(Request $request)
    {
        $query = RevenueReportItem::with(['invoice', 'vatInvoice', 'order'])->orderBy('id', 'desc');

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
                    ->orWhereHas('order', function ($oq) use ($s) {
                        $oq->where('customer_name', 'like', "%{$s}%");
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
    #[OA\Get(
        path: '/reports/revenue-items/{id}',
        summary: 'Chi tiết một dòng doanh thu',
        description: 'Yêu cầu quyền finance.view hoặc invoice.view.',
        tags: ['Báo cáo'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Dòng doanh thu'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.view hoặc invoice.view'),
            new OA\Response(response: 404, description: 'Không tìm thấy dòng báo cáo doanh thu'),
        ]
    )]
    #[OA\Get(
        path: '/revenue-report-items/{id}',
        summary: 'Chi tiết một dòng doanh thu (đường dẫn cũ)',
        description: 'Giống /reports/revenue-items/{id}.',
        tags: ['Báo cáo'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Dòng doanh thu'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 404, description: 'Không tìm thấy dòng báo cáo doanh thu'),
        ]
    )]
    public function show($id)
    {
        $item = RevenueReportItem::with(['invoice', 'vatInvoice', 'order'])->find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy dòng báo cáo doanh thu'], 404);
        }

        return response()->json(['success' => true, 'data' => $item]);
    }
}
