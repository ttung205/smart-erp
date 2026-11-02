<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashFund;
use App\Models\FinanceVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TreasuryController extends Controller
{
    #[OA\Get(
        path: '/master-data',
        summary: 'Danh mục quỹ tiền mặt và tài khoản ngân hàng',
        description: 'Yêu cầu quyền finance.view. Chỉ trả về quỹ và tài khoản đang hoạt động.',
        tags: ['Quỹ và ngân hàng'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'cash_funds và bank_accounts kèm tài khoản kế toán liên kết'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.view'),
        ]
    )]
    public function masterData(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'cash_funds' => CashFund::with('account')->where('is_active', true)->get(),
            'bank_accounts' => BankAccount::with('account')->where('is_active', true)->get(),
        ]]);
    }

    #[OA\Get(
        path: '/cash-book',
        summary: 'Sổ quỹ tiền mặt',
        description: 'Yêu cầu quyền finance.view. Chỉ tính chứng từ đã ghi sổ (POSTED). Không truyền cash_fund_id thì lấy quỹ đang hoạt động đầu tiên.',
        tags: ['Quỹ và ngân hàng'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'cash_fund_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Các dòng thu/chi kèm số dư lũy kế, số dư đầu và cuối'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.view'),
            new OA\Response(response: 404, description: 'Không tìm thấy quỹ tiền mặt'),
        ]
    )]
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

    #[OA\Get(
        path: '/bank-book',
        summary: 'Sổ tiền gửi ngân hàng',
        description: 'Yêu cầu quyền finance.view. Chỉ tính chứng từ đã ghi sổ (POSTED), gồm cả chuyển khoản nội bộ. Không truyền bank_account_id thì lấy tài khoản đang hoạt động đầu tiên.',
        tags: ['Quỹ và ngân hàng'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'bank_account_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Các dòng thu/chi kèm số dư lũy kế, số dư đầu và cuối'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.view'),
            new OA\Response(response: 404, description: 'Không tìm thấy tài khoản ngân hàng'),
        ]
    )]
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
