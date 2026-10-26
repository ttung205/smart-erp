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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $account = ChartOfAccount::findOrFail($data['account_id']);
        $base = GeneralLedgerEntry::query()
            ->where(fn ($query) => $query->where('debit_account_id', $account->id)->orWhere('credit_account_id', $account->id))
            ->where('currency', $data['currency'] ?? 'VND');
        $openingBalance = isset($data['from'])
            ? $this->balanceFor((clone $base)->whereDate('entry_date', '<', $data['from']), $account->id)
            : 0.0;
        $period = (clone $base)
            ->when(isset($data['from']), fn ($query) => $query->whereDate('entry_date', '>=', $data['from']))
            ->when(isset($data['to']), fn ($query) => $query->whereDate('entry_date', '<=', $data['to']));
        $totals = (clone $period)->selectRaw(
            'COALESCE(SUM(CASE WHEN debit_account_id = ? THEN amount ELSE 0 END), 0) AS total_debit,
             COALESCE(SUM(CASE WHEN credit_account_id = ? THEN amount ELSE 0 END), 0) AS total_credit',
            [$account->id, $account->id]
        )->first();
        $totalDebit = (float) $totals->total_debit;
        $totalCredit = (float) $totals->total_credit;
        $entries = (clone $period)->with(['debitAccount', 'creditAccount'])
            ->orderBy('entry_date')->orderBy('id')->paginate($data['per_page'] ?? 20);
        $balance = (float) $openingBalance;
        $firstEntry = $entries->getCollection()->first();
        if ($firstEntry && $entries->currentPage() > 1) {
            $balance += $this->balanceFor(
                (clone $period)->where(function ($query) use ($firstEntry): void {
                    $query->whereDate('entry_date', '<', $firstEntry->entry_date)
                        ->orWhere(function ($sameDate) use ($firstEntry): void {
                            $sameDate->whereDate('entry_date', $firstEntry->entry_date)->where('id', '<', $firstEntry->id);
                        });
                }),
                $account->id
            );
        }
        $rows = $entries->getCollection()->map(function (GeneralLedgerEntry $entry) use ($account, &$balance): array {
            $debit = $entry->debit_account_id === $account->id ? $entry->amount : 0;
            $credit = $entry->credit_account_id === $account->id ? $entry->amount : 0;
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
                'total_credit' => $totalCredit, 'closing_balance' => $openingBalance + $totalDebit - $totalCredit],
            'meta' => ['current_page' => $entries->currentPage(), 'per_page' => $entries->perPage(),
                'total' => $entries->total(), 'last_page' => $entries->lastPage()]]);
    }

    private function balanceFor($query, int $accountId): float
    {
        $result = $query->selectRaw(
            'COALESCE(SUM(CASE WHEN debit_account_id = ? THEN amount ELSE -amount END), 0) AS balance',
            [$accountId]
        )->first();

        return (float) $result->balance;
    }
}
