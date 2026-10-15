<?php

namespace App\Services;

use App\Models\ChartOfAccount;
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
        if ($voucher->status !== 'DRAFT') {
            throw ValidationException::withMessages(['voucher' => 'Chỉ chứng từ bản nháp mới được ghi sổ.']);
        }

        return DB::transaction(function () use ($voucher, $actor): FinanceVoucher {
            $lockedVoucher = FinanceVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $allocatedTotal = 0.0;
            $customerId = null;
            foreach ($lockedVoucher->allocations as $allocation) {
                $receivable = CustomerReceivable::query()->lockForUpdate()->findOrFail($allocation->receivable_id);
                $customerId ??= $receivable->customer_id;
                if ($receivable->customer_id !== $customerId) {
                    throw ValidationException::withMessages(['allocations' => 'Một phiếu thu chỉ được đối trừ hóa đơn của cùng một khách hàng.']);
                }
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

    /** @param array<int, array{receivable_id: int, amount: float|int}> $allocations */
    public function allocate(FinanceVoucher $voucher, array $allocations, object $actor): FinanceVoucher
    {
        return DB::transaction(function () use ($voucher, $allocations, $actor): FinanceVoucher {
            $lockedVoucher = FinanceVoucher::query()->with(['allocations', 'lines'])->lockForUpdate()->findOrFail($voucher->id);
            if ($lockedVoucher->status !== 'POSTED' || ! in_array($lockedVoucher->voucher_type, ['CASH_RECEIPT', 'BANK_RECEIPT'], true)) {
                throw ValidationException::withMessages(['voucher' => 'Chỉ phiếu thu đã ghi sổ mới được đối trừ công nợ.']);
            }

            $remainingAmount = (float) $lockedVoucher->total_amount - (float) $lockedVoucher->allocations->sum('amount');
            $allocationTotal = (float) collect($allocations)->sum('amount');
            if ($allocationTotal <= 0 || $allocationTotal > $remainingAmount + 0.01) {
                throw ValidationException::withMessages(['allocations' => 'Tổng đối trừ vượt quá số tiền chưa phân bổ của phiếu thu.']);
            }

            $customerId = $lockedVoucher->party_type === 'CUSTOMER' ? $lockedVoucher->party_id : null;
            $customerName = null;
            foreach ($allocations as $allocation) {
                $receivable = CustomerReceivable::query()->lockForUpdate()->findOrFail($allocation['receivable_id']);
                $customerId ??= $receivable->customer_id;
                $customerName ??= $receivable->customer_name;
                if ($receivable->customer_id !== $customerId) {
                    throw ValidationException::withMessages(['allocations' => 'Một lần đối trừ chỉ áp dụng cho hóa đơn của cùng một khách hàng.']);
                }
                $amount = (float) $allocation['amount'];
                if ($amount <= 0 || $amount > $receivable->outstanding_amount + 0.01) {
                    throw ValidationException::withMessages(['allocations' => 'Số tiền đối trừ vượt quá công nợ còn lại.']);
                }

                $existingAllocation = $lockedVoucher->allocations()->where('receivable_id', $receivable->id)->first();
                if ($existingAllocation) {
                    $existingAllocation->increment('amount', $amount);
                    $existingAllocation->update(['allocated_at' => now()]);
                } else {
                    $lockedVoucher->allocations()->create(['receivable_id' => $receivable->id, 'amount' => $amount, 'allocated_at' => now()]);
                }
                $receivable->paid_amount += $amount;
                $receivable->outstanding_amount -= $amount;
                $receivable->status = $receivable->outstanding_amount <= 0 ? 'PAID' : 'PARTIALLY_PAID';
                $receivable->save();
            }

            $suspenseAccountId = ChartOfAccount::where('code', '138')->value('id');
            $receivableAccountId = ChartOfAccount::where('code', '1311')->value('id');
            if ($lockedVoucher->lines->contains('credit_account_id', $suspenseAccountId)) {
                $lockedVoucher->lines()->create([
                    'debit_account_id' => $suspenseAccountId,
                    'credit_account_id' => $receivableAccountId,
                    'amount' => $allocationTotal,
                    'description' => 'Đối trừ công nợ khách hàng từ tiền chờ phân bổ',
                ]);
            }

            $lockedVoucher->update([
                'party_type' => 'CUSTOMER', 'party_id' => $customerId,
                'party_name' => $customerName ?: $lockedVoucher->party_name,
                'posted_by_id' => $actor->id, 'posted_by_name' => $actor->name,
            ]);

            return $lockedVoucher->fresh(['lines', 'allocations']);
        });
    }
}
