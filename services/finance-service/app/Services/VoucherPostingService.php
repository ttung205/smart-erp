<?php

namespace App\Services;

use App\Models\CustomerReceivable;
use App\Models\FinanceVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherPostingService
{
    public function post(FinanceVoucher $voucher, object $actor): FinanceVoucher
    {
        if ($voucher->status === 'POSTED') {
            return $voucher->load('lines', 'allocations');
        }

        return DB::transaction(function () use ($voucher, $actor): FinanceVoucher {
            $lockedVoucher = FinanceVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $allocatedTotal = 0.0;
            foreach ($lockedVoucher->allocations as $allocation) {
                $receivable = CustomerReceivable::query()->lockForUpdate()->findOrFail($allocation->receivable_id);
                if ($allocation->amount <= 0 || $allocation->amount > $receivable->outstanding_amount) {
                    throw ValidationException::withMessages(['allocations' => 'Số tiền đối trừ vượt quá công nợ còn lại.']);
                }
                $receivable->paid_amount += $allocation->amount;
                $receivable->outstanding_amount -= $allocation->amount;
                $receivable->status = $receivable->outstanding_amount <= 0 ? 'PAID' : 'PARTIALLY_PAID';
                $receivable->save();
                $allocatedTotal += $allocation->amount;
            }
            if ($lockedVoucher->allocations->isNotEmpty() && abs($allocatedTotal - $lockedVoucher->total_amount) > 0.01) {
                throw ValidationException::withMessages(['allocations' => 'Tổng phân bổ phải bằng tổng tiền chứng từ.']);
            }
            $lockedVoucher->update([
                'status' => 'POSTED', 'posted_by_id' => $actor->id,
                'posted_by_name' => $actor->name, 'posted_at' => now(),
            ]);

            return $lockedVoucher->fresh(['lines', 'allocations']);
        });
    }
}
