<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneralLedgerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);
        $account = ChartOfAccount::findOrFail($data['account_id']);
        $base = GeneralLedgerEntry::query()
            ->where(fn ($query) => $query->where('debit_account_id', $account->id)->orWhere('credit_account_id', $account->id))
            ->where('currency', $data['currency'] ?? 'VND');
        $openingEntries = isset($data['from'])
            ? (clone $base)->whereDate('entry_date', '<', $data['from'])->get() : collect();
        $openingBalance = $openingEntries->sum(fn (GeneralLedgerEntry $entry): float => ($entry->debit_account_id === $account->id ? $entry->amount : 0)
            - ($entry->credit_account_id === $account->id ? $entry->amount : 0));
        $entries = (clone $base)
            ->when(isset($data['from']), fn ($query) => $query->whereDate('entry_date', '>=', $data['from']))
            ->when(isset($data['to']), fn ($query) => $query->whereDate('entry_date', '<=', $data['to']))
            ->with(['debitAccount', 'creditAccount'])->orderBy('entry_date')->orderBy('id')->get();
        $balance = (float) $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $rows = $entries->map(function (GeneralLedgerEntry $entry) use ($account, &$balance, &$totalDebit, &$totalCredit): array {
            $debit = $entry->debit_account_id === $account->id ? $entry->amount : 0;
            $credit = $entry->credit_account_id === $account->id ? $entry->amount : 0;
            $totalDebit += $debit;
            $totalCredit += $credit;
            $balance += $debit - $credit;

            return [
                'id' => $entry->id, 'entry_date' => $entry->entry_date,
                'document_number' => $entry->document_number, 'source_type' => $entry->source_type,
                'description' => $entry->description,
                'counter_account_code' => $debit > 0 ? $entry->creditAccount?->code : $entry->debitAccount?->code,
                'debit' => $debit, 'credit' => $credit, 'balance' => $balance,
            ];
        });

        return response()->json(['success' => true, 'account' => $account, 'data' => $rows,
            'summary' => ['opening_balance' => $openingBalance, 'total_debit' => $totalDebit,
                'total_credit' => $totalCredit, 'closing_balance' => $balance]]);
    }
}
