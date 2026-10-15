<?php

namespace App\Http\Controllers;

use App\Models\CustomerReceivable;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceAdjustmentSyncController extends Controller
{
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
