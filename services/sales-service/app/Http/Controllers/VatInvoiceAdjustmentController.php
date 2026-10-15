<?php

namespace App\Http\Controllers;

use App\Models\SaleReturn;
use App\Models\VatInvoiceAdjustment;
use App\Services\CustomerReceivableService;
use App\Services\VatInvoiceAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VatInvoiceAdjustmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VatInvoiceAdjustment::with(['originalInvoice', 'issuedInvoice', 'items.originalItem', 'saleReturn'])->latest('id');
        if ($request->filled('original_vat_invoice_id')) {
            $query->where('original_vat_invoice_id', $request->integer('original_vat_invoice_id'));
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function show(VatInvoiceAdjustment $adjustment): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $adjustment->load(['originalInvoice.items', 'issuedInvoice.items', 'items.originalItem', 'saleReturn'])]);
    }

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

    public function fromSaleReturn(Request $request, SaleReturn $saleReturn, VatInvoiceAdjustmentService $service): JsonResponse
    {
        $adjustments = $service->createForReturn($saleReturn->load('items'), $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $adjustments], 201);
    }

    public function issue(Request $request, VatInvoiceAdjustment $adjustment, VatInvoiceAdjustmentService $service, CustomerReceivableService $receivables): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $service->issue($adjustment, (string) $request->bearerToken(), $receivables)]);
    }

    public function approve(Request $request, VatInvoiceAdjustment $adjustment, VatInvoiceAdjustmentService $service): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $service->approve($adjustment, $request->attributes->get('actor'))]);
    }
}
