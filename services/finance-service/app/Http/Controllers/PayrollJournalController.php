<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nhận bút toán ghi nhận chi phí lương từ eoffice-service (Nợ 642 / Có 334, các khoản giữ lại...).
 * Idempotent theo `reference`: gọi lại cùng kỳ không ghi trùng sổ cái.
 */
class PayrollJournalController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.debit_account_code' => ['required', 'exists:chart_of_accounts,code'],
            'lines.*.credit_account_code' => ['required', 'exists:chart_of_accounts,code'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $codes = collect($data['lines'])->flatMap(fn (array $line): array => [$line['debit_account_code'], $line['credit_account_code']])->unique();
        $accounts = ChartOfAccount::whereIn('code', $codes)->where('is_postable', true)->get()->keyBy('code');
        if ($accounts->count() !== $codes->count()) {
            throw ValidationException::withMessages(['lines' => 'Chỉ được hạch toán vào tài khoản chi tiết.']);
        }

        $documentNumber = 'LUONG-'.$data['reference'];
        $entries = DB::transaction(function () use ($data, $accounts, $documentNumber) {
            $entries = [];
            foreach (array_values($data['lines']) as $index => $line) {
                $entries[] = GeneralLedgerEntry::firstOrCreate(['source_key' => "PAYROLL:{$data['reference']}:{$index}"], [
                    'source_type' => 'PAYROLL', 'source_id' => 0, 'document_number' => $documentNumber,
                    'entry_date' => $data['entry_date'],
                    'debit_account_id' => $accounts[$line['debit_account_code']]->id,
                    'credit_account_id' => $accounts[$line['credit_account_code']]->id,
                    'amount' => $line['amount'], 'currency' => 'VND',
                    'description' => $line['description'],
                ]);
            }

            return $entries;
        });

        return response()->json(['success' => true, 'data' => [
            'document_number' => $documentNumber,
            'entries' => count($entries),
            'created' => collect($entries)->where('wasRecentlyCreated', true)->count(),
        ]], 201);
    }
}
