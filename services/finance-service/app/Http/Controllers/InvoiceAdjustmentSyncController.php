<?php

namespace App\Http\Controllers;

use App\Models\CustomerReceivable;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class InvoiceAdjustmentSyncController extends Controller
{
    #[OA\Post(
        path: '/invoice-adjustments/sync',
        summary: 'Ghi nhận điều chỉnh hóa đơn GTGT vào công nợ',
        description: 'Yêu cầu quyền finance.approve hoặc invoice.manage. Idempotent theo adjustment_id: gửi lại cùng giá trị không ghi trùng, khác giá trị thì trả 422.',
        tags: ['Công nợ phải thu'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['adjustment_id', 'original_vat_invoice_id', 'adjustment_number', 'adjustment_date', 'reason', 'adjustment_type', 'subtotal_delta', 'vat_delta', 'total_delta', 'currency'],
                properties: [
                    new OA\Property(property: 'adjustment_id', type: 'integer'),
                    new OA\Property(property: 'original_vat_invoice_id', type: 'integer'),
                    new OA\Property(property: 'adjustment_number', type: 'string', maxLength: 50),
                    new OA\Property(property: 'adjustment_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'reason', type: 'string'),
                    new OA\Property(property: 'adjustment_type', type: 'string', enum: ['PRICE', 'VAT', 'QUANTITY']),
                    new OA\Property(property: 'subtotal_delta', type: 'number', description: 'Âm khi điều chỉnh giảm'),
                    new OA\Property(property: 'vat_delta', type: 'number'),
                    new OA\Property(property: 'total_delta', type: 'number', description: 'Bằng subtotal_delta + vat_delta'),
                    new OA\Property(property: 'currency', type: 'string', example: 'VND'),
                    new OA\Property(property: 'customer_id', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Công nợ sau điều chỉnh'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.approve hoặc invoice.manage'),
            new OA\Response(response: 404, description: 'Không tìm thấy công nợ của hóa đơn gốc'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: chênh lệch không khớp, khác tiền tệ, giảm vượt giá trị hóa đơn hoặc adjustment_id đã ghi với giá trị khác'),
        ]
    )]
    public function store(Request $request, GeneralLedgerService $ledger): JsonResponse
    {
        $data = $request->validate([
            'adjustment_id' => ['required', 'integer'], 'original_vat_invoice_id' => ['required', 'integer'],
            'adjustment_number' => ['required', 'string', 'max:50'],
            'adjustment_date' => ['required', 'date'], 'reason' => ['required', 'string'],
            'adjustment_type' => ['required', 'in:PRICE,VAT,QUANTITY'],
            'subtotal_delta' => ['required', 'numeric'], 'vat_delta' => ['required', 'numeric'],
            'total_delta' => ['required', 'numeric'], 'currency' => ['required', 'string'],
            'customer_id' => ['nullable', 'integer'],
        ]);
        if (abs((float) $data['subtotal_delta'] + (float) $data['vat_delta'] - (float) $data['total_delta']) > 0.01) {
            throw ValidationException::withMessages(['total_delta' => 'Tổng chênh lệch không khớp với tiền hàng và thuế.']);
        }

        $receivable = DB::transaction(function () use ($data, $ledger): CustomerReceivable {
            $receivable = CustomerReceivable::query()->where('source_key', 'VAT:'.$data['original_vat_invoice_id'])->lockForUpdate()->firstOrFail();
            $existing = DB::table('receivable_adjustments')->where('source_adjustment_id', $data['adjustment_id'])->first();
            if ($existing) {
                if ($existing->receivable_id !== $receivable->id || abs((float) $existing->total_delta - (float) $data['total_delta']) > 0.01) {
                    throw ValidationException::withMessages(['adjustment_id' => 'Yêu cầu điều chỉnh đã được ghi nhận với giá trị khác.']);
                }

                return $receivable;
            }
            if ($receivable->currency !== $data['currency']) {
                throw ValidationException::withMessages(['currency' => 'Tiền tệ điều chỉnh không khớp hóa đơn gốc.']);
            }
            $newAmount = round($receivable->original_amount + (float) $data['total_delta'], 2);
            if ($newAmount < 0) {
                throw ValidationException::withMessages(['total_delta' => 'Điều chỉnh giảm vượt quá giá trị hóa đơn.']);
            }
            $receivable->original_amount = $newAmount;
            $receivable->outstanding_amount = round($newAmount - $receivable->paid_amount, 2);
            $receivable->status = $receivable->outstanding_amount < 0 ? 'CREDIT'
                : ($receivable->outstanding_amount == 0 ? 'PAID'
                    : ($receivable->paid_amount > 0 ? 'PARTIALLY_PAID' : 'OPEN'));
            $receivable->save();
            DB::table('receivable_adjustments')->insert([
                'source_adjustment_id' => $data['adjustment_id'], 'receivable_id' => $receivable->id,
                'total_delta' => $data['total_delta'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            $ledger->recordVatAdjustment($data);

            return $receivable;
        });

        return response()->json(['success' => true, 'data' => $receivable]);
    }
}
