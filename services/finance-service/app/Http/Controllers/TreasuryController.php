<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashFund;
use App\Models\FinanceVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    public function masterData(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'cash_funds' => CashFund::with('account')->where('is_active', true)->get(),
            'bank_accounts' => BankAccount::with('account')->where('is_active', true)->get(),
        ]]);
    }

    public function cashBook(Request $request): JsonResponse
    {
        $fundId = $request->integer('cash_fund_id') ?: CashFund::where('is_active', true)->value('id');
        $fund = CashFund::findOrFail($fundId);
        $entries = FinanceVoucher::query()->with('lines')->where('cash_fund_id', $fundId)->where('status', 'POSTED')->orderBy('voucher_date')->orderBy('id')->get();
        $balance = (float) $fund->opening_balance;
        $rows = $entries->map(function (FinanceVoucher $voucher) use (&$balance): array {
            $receipt = str_contains($voucher->voucher_type, 'RECEIPT') ? $voucher->total_amount : 0;
            $payment = str_contains($voucher->voucher_type, 'PAYMENT') ? $voucher->total_amount : 0;
            $balance += $receipt - $payment;

            return ['id' => $voucher->id, 'voucher_number' => $voucher->voucher_number, 'voucher_date' => $voucher->voucher_date, 'description' => $voucher->description, 'party_name' => $voucher->party_name, 'receipt' => $receipt, 'payment' => $payment, 'balance' => $balance];
        });

        return response()->json(['success' => true, 'data' => $rows, 'summary' => ['opening_balance' => (float) $fund->opening_balance, 'closing_balance' => $balance]]);
    }

    public function bankBook(Request $request): JsonResponse
    {
        $accountId = $request->integer('bank_account_id') ?: BankAccount::where('is_active', true)->value('id');
        $account = BankAccount::findOrFail($accountId);
        $entries = FinanceVoucher::query()->where('status', 'POSTED')->where(fn ($query) => $query->where('bank_account_id', $accountId)->orWhere('destination_bank_account_id', $accountId))->orderBy('voucher_date')->orderBy('id')->get();
        $balance = (float) $account->opening_balance;
        $rows = $entries->map(function (FinanceVoucher $voucher) use (&$balance, $accountId): array {
            $receipt = $voucher->destination_bank_account_id === $accountId || $voucher->voucher_type === 'BANK_RECEIPT' ? $voucher->total_amount : 0;
            $payment = $voucher->bank_account_id === $accountId && in_array($voucher->voucher_type, ['BANK_PAYMENT', 'INTERNAL_TRANSFER'], true) ? $voucher->total_amount : 0;
            $balance += $receipt - $payment;

            return ['id' => $voucher->id, 'voucher_number' => $voucher->voucher_number, 'voucher_date' => $voucher->voucher_date, 'description' => $voucher->description, 'party_name' => $voucher->party_name, 'receipt' => $receipt, 'payment' => $payment, 'balance' => $balance];
        });

        return response()->json(['success' => true, 'data' => $rows, 'summary' => ['opening_balance' => (float) $account->opening_balance, 'closing_balance' => $balance]]);
    }
}
