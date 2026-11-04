<?php

namespace App\Http\Controllers;

use App\Models\SaleReturn;
use App\Models\VatInvoiceAdjustment;
use App\Services\CustomerReceivableService;
use App\Services\VatInvoiceAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class VatInvoiceAdjustmentController extends Controller
{
    #[OA\Get(
        path: '/vat-invoice-adjustments',
        summary: 'Danh sách điều chỉnh hóa đơn GTGT',
        description: 'Yêu cầu quyền invoice.view hoặc finance.view.',
        tags: ['Hóa đơn GTGT'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'original_vat_invoice_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Các điều chỉnh'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền invoice.view hoặc finance.view'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = VatInvoiceAdjustment::with(['originalInvoice', 'issuedInvoice', 'items.originalItem', 'saleReturn'])->latest('id');
        if ($request->filled('original_vat_invoice_id')) {
            $query->where('original_vat_invoice_id', $request->integer('original_vat_invoice_id'));
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    #[OA\Get(
        path: '/vat-invoice-adjustments/{adjustment}',
        summary: 'Chi tiết điều chỉnh hóa đơn GTGT',
        description: 'Yêu cầu quyền invoice.view hoặc finance.view.',
        tags: ['Hóa đơn GTGT'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'adjustment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Điều chỉnh kèm dòng hàng'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền invoice.view hoặc finance.view'),
            new OA\Response(response: 404, description: self::DOC_404),
        ]
    )]
    public function show(VatInvoiceAdjustment $adjustment): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $adjustment->load(['originalInvoice.items', 'issuedInvoice.items', 'items.originalItem', 'saleReturn'])]);
    }

    #[OA\Post(
        path: '/vat-invoice-adjustments',
        summary: 'Lập điều chỉnh hóa đơn GTGT (nháp)',
        description: 'Yêu cầu quyền finance.create. Chỉ điều chỉnh hóa đơn đã phát hành; điều chỉnh giá (PRICE) hoặc thuế suất (VAT), tăng hoặc giảm.',
        tags: ['Hóa đơn GTGT'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['original_vat_invoice_id', 'direction', 'adjustment_type', 'adjustment_date', 'reason', 'items'],
                properties: [
                    new OA\Property(property: 'original_vat_invoice_id', type: 'integer'),
                    new OA\Property(property: 'direction', type: 'string', enum: ['INCREASE', 'DECREASE']),
                    new OA\Property(property: 'adjustment_type', type: 'string', enum: ['PRICE', 'VAT']),
                    new OA\Property(property: 'adjustment_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'reason', type: 'string', maxLength: 2000),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        minItems: 1,
                        items: new OA\Items(
                            required: ['original_vat_invoice_item_id', 'amount'],
                            properties: [
                                new OA\Property(property: 'original_vat_invoice_item_id', type: 'integer'),
                                new OA\Property(property: 'amount', type: 'number', exclusiveMinimum: 0, description: 'Số tiền (PRICE) hoặc điểm phần trăm thuế (VAT)'),
                            ],
                            type: 'object'
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Điều chỉnh nháp'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.create'),
            new OA\Response(response: 422, description: 'Hóa đơn chưa phát hành, dòng không thuộc hóa đơn gốc hoặc mức điều chỉnh không hợp lệ'),
        ]
    )]
    public function store(Request $request, VatInvoiceAdjustmentService $service): JsonResponse
    {
        $data = $request->validate([
            'original_vat_invoice_id' => ['required', 'integer', 'exists:vat_invoices,id'],
            'direction' => ['required', 'in:INCREASE,DECREASE'],
            'adjustment_type' => ['required', 'in:PRICE,VAT'],
            'adjustment_date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.original_vat_invoice_item_id' => ['required', 'integer', 'distinct', 'exists:vat_invoice_items,id'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json(['success' => true, 'data' => $service->createDraft($data, $request->attributes->get('actor'))], 201);
    }

    #[OA\Post(
        path: '/sale-returns/{saleReturn}/vat-invoice-adjustments',
        summary: 'Lập điều chỉnh giảm từ phiếu trả hàng',
        description: 'Yêu cầu quyền finance.create. Sinh các điều chỉnh giảm số lượng cho các hóa đơn GTGT liên quan đến hàng trả.',
        tags: ['Hóa đơn GTGT'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'saleReturn', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Các điều chỉnh nháp'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.create'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: 'Số lượng trả vượt số lượng hóa đơn còn được điều chỉnh'),
        ]
    )]
    public function fromSaleReturn(Request $request, SaleReturn $saleReturn, VatInvoiceAdjustmentService $service): JsonResponse
    {
        $adjustments = $service->createForReturn($saleReturn->load('items'), $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $adjustments], 201);
    }

    #[OA\Post(
        path: '/vat-invoice-adjustments/{adjustment}/issue',
        summary: 'Phát hành điều chỉnh hóa đơn GTGT',
        description: 'Yêu cầu quyền finance.approve. Phát hành điều chỉnh đã duyệt và đồng bộ chênh lệch sang công nợ bên finance-service.',
        tags: ['Hóa đơn GTGT'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'adjustment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Điều chỉnh đã phát hành'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.approve'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: 'Điều chỉnh không ở trạng thái được phát hành, hoặc giá trị đã thay đổi do điều chỉnh khác'),
        ]
    )]
    public function issue(Request $request, VatInvoiceAdjustment $adjustment, VatInvoiceAdjustmentService $service, CustomerReceivableService $receivables): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $service->issue($adjustment, (string) $request->bearerToken(), $receivables)]);
    }

    #[OA\Post(
        path: '/vat-invoice-adjustments/{adjustment}/approve',
        summary: 'Duyệt điều chỉnh hóa đơn GTGT',
        description: 'Yêu cầu quyền finance.approve. Chỉ điều chỉnh đang chờ duyệt.',
        tags: ['Hóa đơn GTGT'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'adjustment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Điều chỉnh đã duyệt'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.approve'),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: 'Điều chỉnh không ở trạng thái chờ duyệt'),
        ]
    )]
    public function approve(Request $request, VatInvoiceAdjustment $adjustment, VatInvoiceAdjustmentService $service): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $service->approve($adjustment, $request->attributes->get('actor'))]);
    }
}
