<?php

namespace App\Http\Controllers;

use App\Models\CustomerReceivable;
use App\Services\GeneralLedgerService;
use App\Services\ReceivableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CustomerReceivableController extends Controller
{
    #[OA\Get(
        path: '/customer-receivables',
        summary: 'Danh sách công nợ phải thu khách hàng',
        description: 'Yêu cầu quyền finance.view. Kèm tổng hợp toàn bộ công nợ (summary) không phụ thuộc bộ lọc.',
        tags: ['Công nợ phải thu'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Tìm theo số hóa đơn, tên hoặc mã số thuế khách hàng', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Trạng thái công nợ; OVERDUE lọc khoản còn nợ đã quá hạn', schema: new OA\Schema(type: 'string', enum: ['OPEN', 'PARTIALLY_PAID', 'PAID', 'CREDIT', 'OVERDUE'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50, maximum: 200, minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách công nợ, tổng số bản ghi, summary và phân trang'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.view'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = CustomerReceivable::with('account')->orderByDesc('invoice_date')->orderByDesc('id');
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn ($item) => $item->where('invoice_number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('customer_tax_code', 'like', "%{$search}%"));
        }
        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if ($status === 'OVERDUE') {
                $query->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', today());
            } else {
                $query->where('status', $status);
            }
        }
        $receivables = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));
        $base = CustomerReceivable::query();

        return response()->json([
            'success' => true, 'data' => $receivables->items(), 'total' => $receivables->total(),
            'summary' => [
                'total_receivable' => (float) (clone $base)->sum('original_amount'),
                'total_paid' => (float) (clone $base)->sum('paid_amount'),
                'total_outstanding' => (float) (clone $base)->where('outstanding_amount', '>', 0)->sum('outstanding_amount'),
                'total_credit' => -(float) (clone $base)->where('outstanding_amount', '<', 0)->sum('outstanding_amount'),
                'overdue_outstanding' => (float) (clone $base)->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', today())->sum('outstanding_amount'),
            ],
            'meta' => ['current_page' => $receivables->currentPage(), 'last_page' => $receivables->lastPage(), 'per_page' => $receivables->perPage()],
        ]);
    }

    #[OA\Post(
        path: '/customer-receivables/sync',
        summary: 'Ghi nhận công nợ từ hóa đơn GTGT',
        description: 'Yêu cầu quyền finance.approve hoặc invoice.manage. Sales gọi khi phát hành hóa đơn GTGT. Tiền hàng + thuế phải bằng tổng hóa đơn (sai lệch tối đa 0,01).',
        tags: ['Công nợ phải thu'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['vat_invoice_id', 'customer_name', 'invoice_number', 'invoice_date', 'total_amount'],
                properties: [
                    new OA\Property(property: 'vat_invoice_id', type: 'integer'),
                    new OA\Property(property: 'sales_invoice_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'customer_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'customer_name', type: 'string'),
                    new OA\Property(property: 'customer_tax_code', type: 'string', nullable: true),
                    new OA\Property(property: 'invoice_number', type: 'string'),
                    new OA\Property(property: 'invoice_series', type: 'string', nullable: true),
                    new OA\Property(property: 'invoice_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'currency', type: 'string', nullable: true, example: 'VND'),
                    new OA\Property(property: 'total_amount', type: 'number', exclusiveMinimum: 0),
                    new OA\Property(property: 'subtotal', type: 'number', nullable: true, description: 'Mặc định bằng total_amount'),
                    new OA\Property(property: 'vat_amount', type: 'number', nullable: true, description: 'Mặc định 0'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Công nợ đã được ghi nhận'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.approve hoặc invoice.manage'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED, hoặc tiền hàng và thuế không khớp tổng hóa đơn'),
        ]
    )]
    public function sync(Request $request, ReceivableService $service, GeneralLedgerService $ledger): JsonResponse
    {
        $data = $request->validate([
            'vat_invoice_id' => ['required', 'integer'], 'sales_invoice_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'], 'customer_name' => ['required', 'string'],
            'customer_tax_code' => ['nullable', 'string'], 'invoice_number' => ['required', 'string'],
            'invoice_series' => ['nullable', 'string'], 'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'], 'currency' => ['nullable', 'string'],
            'total_amount' => ['required', 'numeric', 'gt:0'],
            'subtotal' => ['nullable', 'numeric', 'gte:0'], 'vat_amount' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $data['subtotal'] ??= $data['total_amount'];
        $data['vat_amount'] ??= 0;
        if (abs((float) $data['subtotal'] + (float) $data['vat_amount'] - (float) $data['total_amount']) > 0.01) {
            return response()->json(['success' => false, 'message' => 'Tiền hàng và thuế không khớp tổng hóa đơn.'], 422);
        }

        return response()->json(['success' => true, 'data' => $service->syncVatInvoice($data, $ledger)], 201);
    }
}
