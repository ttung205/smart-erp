<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashFund;
use App\Models\ChartOfAccount;
use App\Models\FinanceVoucher;
use App\Services\GeneralLedgerService;
use App\Services\VoucherPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinanceVoucherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = FinanceVoucher::with(['lines', 'allocations'])->orderByDesc('voucher_date')->orderByDesc('id');
        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }
        if ($request->filled('voucher_type')) {
            $query->where('voucher_type', $request->input('voucher_type'));
        }

        $vouchers = $query->paginate($data['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => $vouchers->items(),
            'meta' => [
                'current_page' => $vouchers->currentPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'last_page' => $vouchers->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['nullable', 'uuid'], 'voucher_type' => ['required', 'in:CASH_RECEIPT,CASH_PAYMENT,BANK_RECEIPT,BANK_PAYMENT,INTERNAL_TRANSFER'],
            'channel' => ['required', 'in:CASH,BANK'], 'voucher_date' => ['required', 'date'], 'accounting_date' => ['nullable', 'date'],
            'party_type' => ['nullable', 'required_unless:voucher_type,INTERNAL_TRANSFER', 'in:CUSTOMER,SUPPLIER,OTHER'],
            'party_id' => ['nullable', 'integer'], 'party_name' => ['nullable', 'required_unless:voucher_type,INTERNAL_TRANSFER', 'string'],
            'party_code' => ['nullable', 'string', 'max:50'], 'party_address' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'], 'reason_code' => ['nullable', 'string', 'max:50'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'], 'cash_fund_id' => ['nullable', 'required_if:channel,CASH', 'exists:cash_funds,id'],
            'bank_account_id' => ['nullable', 'required_if:channel,BANK', 'exists:bank_accounts,id'], 'total_amount' => ['required', 'numeric', 'gt:0'],
            'destination_bank_account_id' => ['nullable', 'required_if:voucher_type,INTERNAL_TRANSFER', 'different:bank_account_id', 'exists:bank_accounts,id'],
            'currency' => ['nullable', 'in:VND,USD,EUR,GBP,JPY,CNY,KRW,SGD,THB,AUD,CAD,CHF,HKD'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'], 'counter_account_code' => ['nullable', 'exists:chart_of_accounts,code'],
            'allocations' => ['nullable', 'array'], 'allocations.*.receivable_id' => ['required', 'exists:customer_receivables,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines' => ['nullable', 'array', 'min:1'], 'lines.*.description' => ['required', 'string'],
            'lines.*.debit_account_code' => ['required', 'exists:chart_of_accounts,code'],
            'lines.*.credit_account_code' => ['required', 'exists:chart_of_accounts,code'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);
        if ($data['voucher_type'] !== 'INTERNAL_TRANSFER' && $data['party_type'] !== 'OTHER' && empty($data['party_id'])) {
            throw ValidationException::withMessages(['party_id' => 'Vui lòng chọn khách hàng hoặc nhà cung cấp cho chứng từ.']);
        }
        if (! empty($data['lines']) && abs(collect($data['lines'])->sum('amount') - (float) $data['total_amount']) > 0.01) {
            throw ValidationException::withMessages(['lines' => 'Tổng tiền định khoản phải bằng tổng tiền chứng từ.']);
        }
        $idempotencyKey = $data['idempotency_key'] ?? (string) Str::uuid();
        if ($existing = FinanceVoucher::where('idempotency_key', $idempotencyKey)->first()) {
            return response()->json(['success' => true, 'data' => $existing->load('lines', 'allocations')]);
        }
        $actor = $request->attributes->get('actor');
        $voucher = DB::transaction(function () use ($data, $idempotencyKey, $actor): FinanceVoucher {
            $sequence = FinanceVoucher::where('voucher_type', $data['voucher_type'])->count() + 1;
            $prefix = match ($data['voucher_type']) {
                'CASH_RECEIPT' => 'PT', 'CASH_PAYMENT' => 'PC', 'BANK_RECEIPT' => 'BT', 'BANK_PAYMENT' => 'BC', default => 'CK'
            };
            $voucher = FinanceVoucher::create([
                ...collect($data)->except(['counter_account_code', 'allocations', 'lines'])->all(),
                'idempotency_key' => $idempotencyKey, 'voucher_number' => $prefix.'-'.now()->format('Ym').'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
                'status' => 'DRAFT', 'created_by_id' => $actor->id, 'created_by_name' => $actor->name,
            ]);
            $cashAccountId = $data['channel'] === 'CASH' ? CashFund::findOrFail($data['cash_fund_id'])->account_id : BankAccount::findOrFail($data['bank_account_id'])->account_id;
            if (! empty($data['lines'])) {
                $codes = collect($data['lines'])->flatMap(fn (array $line): array => [$line['debit_account_code'], $line['credit_account_code']]);
                $accounts = ChartOfAccount::whereIn('code', $codes)->where('is_postable', true)->get()->keyBy('code');
                foreach ($data['lines'] as $line) {
                    if (! $accounts->has($line['debit_account_code']) || ! $accounts->has($line['credit_account_code'])) {
                        throw ValidationException::withMessages(['lines' => 'Chỉ được hạch toán vào tài khoản chi tiết.']);
                    }
                    $voucher->lines()->create([
                        'debit_account_id' => $accounts[$line['debit_account_code']]->id,
                        'credit_account_id' => $accounts[$line['credit_account_code']]->id,
                        'amount' => $line['amount'],
                        'description' => $line['description'],
                    ]);
                }
            } elseif ($data['voucher_type'] === 'INTERNAL_TRANSFER') {
                $destinationAccountId = BankAccount::findOrFail($data['destination_bank_account_id'])->account_id;
                $voucher->lines()->create(['debit_account_id' => $destinationAccountId, 'credit_account_id' => $cashAccountId, 'amount' => $data['total_amount'], 'description' => $data['description']]);
            } else {
                $counterCode = $data['counter_account_code'] ?? '1311';
                $counterId = ChartOfAccount::where('code', $counterCode)->where('is_postable', true)->value('id');
                $isReceipt = str_contains($data['voucher_type'], 'RECEIPT');
                $voucher->lines()->create(['debit_account_id' => $isReceipt ? $cashAccountId : $counterId, 'credit_account_id' => $isReceipt ? $counterId : $cashAccountId, 'amount' => $data['total_amount'], 'description' => $data['description']]);
            }
            foreach ($data['allocations'] ?? [] as $allocation) {
                $voucher->allocations()->create([...$allocation, 'allocated_at' => now()]);
            }

            return $voucher->load('lines', 'allocations');
        });

        return response()->json(['success' => true, 'data' => $voucher], 201);
    }

    public function post(Request $request, FinanceVoucher $voucher, VoucherPostingService $service, GeneralLedgerService $ledger): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $service->post($voucher, $request->attributes->get('actor'), $ledger)]);
    }

    public function allocate(Request $request, FinanceVoucher $voucher, VoucherPostingService $service, GeneralLedgerService $ledger): JsonResponse
    {
        $data = $request->validate([
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.receivable_id' => ['required', 'integer', 'exists:customer_receivables,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $service->allocate($voucher, $data['allocations'], $request->attributes->get('actor'), $ledger),
        ]);
    }
}
