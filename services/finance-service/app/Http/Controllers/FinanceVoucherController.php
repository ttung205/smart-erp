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
use OpenApi\Attributes as OA;

class FinanceVoucherController extends Controller
{
    #[OA\Get(
        path: '/vouchers',
        summary: 'Danh sách chứng từ thu chi',
        description: 'Yêu cầu quyền finance.view. Kèm định khoản (lines) và phân bổ công nợ (allocations), mới nhất trước.',
        tags: ['Chứng từ thu chi'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'channel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['CASH', 'BANK'])),
            new OA\Parameter(name: 'voucher_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['CASH_RECEIPT', 'CASH_PAYMENT', 'BANK_RECEIPT', 'BANK_PAYMENT', 'INTERNAL_TRANSFER'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách chứng từ và phân trang'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.view'),
        ]
    )]
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

    #[OA\Post(
        path: '/vouchers',
        summary: 'Tạo chứng từ thu chi (bản nháp)',
        description: 'Yêu cầu quyền finance.create hoặc finance.approve. Chứng từ tạo ở trạng thái DRAFT, cần gọi /vouchers/{id}/post để ghi sổ. '
            .'Idempotent theo idempotency_key: gửi lại cùng khóa trả về chứng từ đã tạo (200). '
            .'Không gửi lines thì hệ thống tự định khoản với tài khoản đối ứng counter_account_code (mặc định 1311).',
        tags: ['Chứng từ thu chi'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['voucher_type', 'channel', 'voucher_date', 'description', 'total_amount'],
                properties: [
                    new OA\Property(property: 'idempotency_key', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'voucher_type', type: 'string', enum: ['CASH_RECEIPT', 'CASH_PAYMENT', 'BANK_RECEIPT', 'BANK_PAYMENT', 'INTERNAL_TRANSFER']),
                    new OA\Property(property: 'channel', type: 'string', enum: ['CASH', 'BANK']),
                    new OA\Property(property: 'voucher_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'accounting_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'party_type', type: 'string', enum: ['CUSTOMER', 'SUPPLIER', 'OTHER'], nullable: true, description: 'Bắt buộc trừ khi INTERNAL_TRANSFER'),
                    new OA\Property(property: 'party_id', type: 'integer', nullable: true, description: 'Bắt buộc khi party_type là CUSTOMER hoặc SUPPLIER'),
                    new OA\Property(property: 'party_name', type: 'string', nullable: true, description: 'Bắt buộc trừ khi INTERNAL_TRANSFER'),
                    new OA\Property(property: 'party_code', type: 'string', nullable: true, maxLength: 50),
                    new OA\Property(property: 'party_address', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'contact_name', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'reason_code', type: 'string', nullable: true, maxLength: 50),
                    new OA\Property(property: 'employee_name', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'cash_fund_id', type: 'integer', nullable: true, description: 'Bắt buộc khi channel = CASH'),
                    new OA\Property(property: 'bank_account_id', type: 'integer', nullable: true, description: 'Bắt buộc khi channel = BANK'),
                    new OA\Property(property: 'destination_bank_account_id', type: 'integer', nullable: true, description: 'Bắt buộc khi INTERNAL_TRANSFER, khác bank_account_id'),
                    new OA\Property(property: 'total_amount', type: 'number', exclusiveMinimum: 0),
                    new OA\Property(property: 'currency', type: 'string', nullable: true, enum: ['VND', 'USD', 'EUR', 'GBP', 'JPY', 'CNY', 'KRW', 'SGD', 'THB', 'AUD', 'CAD', 'CHF', 'HKD']),
                    new OA\Property(property: 'exchange_rate', type: 'number', nullable: true, exclusiveMinimum: 0),
                    new OA\Property(property: 'counter_account_code', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'allocations',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            required: ['receivable_id', 'amount'],
                            properties: [
                                new OA\Property(property: 'receivable_id', type: 'integer'),
                                new OA\Property(property: 'amount', type: 'number', exclusiveMinimum: 0),
                            ],
                            type: 'object'
                        )
                    ),
                    new OA\Property(
                        property: 'lines',
                        type: 'array',
                        nullable: true,
                        description: 'Tổng amount phải bằng total_amount',
                        items: new OA\Items(
                            required: ['description', 'debit_account_code', 'credit_account_code', 'amount'],
                            properties: [
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'debit_account_code', type: 'string'),
                                new OA\Property(property: 'credit_account_code', type: 'string'),
                                new OA\Property(property: 'amount', type: 'number', exclusiveMinimum: 0),
                            ],
                            type: 'object'
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Chứng từ bản nháp kèm định khoản và phân bổ'),
            new OA\Response(response: 200, description: 'idempotency_key đã tồn tại, trả về chứng từ đã tạo trước đó'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.create hoặc finance.approve'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: thiếu đối tượng, tổng định khoản lệch tổng chứng từ hoặc hạch toán vào tài khoản tổng hợp'),
        ]
    )]
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

    #[OA\Post(
        path: '/vouchers/{id}/post',
        summary: 'Ghi sổ chứng từ thu chi',
        description: 'Yêu cầu quyền finance.post hoặc finance.approve. Chỉ chứng từ DRAFT mới được ghi sổ. Ghi sổ cái và cập nhật công nợ theo phân bổ.',
        tags: ['Chứng từ thu chi'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Chứng từ sau khi ghi sổ'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.post hoặc finance.approve'),
            new OA\Response(response: 404, description: 'Không tìm thấy chứng từ'),
            new OA\Response(response: 422, description: 'Chứng từ không ở trạng thái nháp, hoặc phân bổ công nợ không hợp lệ'),
        ]
    )]
    public function post(Request $request, FinanceVoucher $voucher, VoucherPostingService $service, GeneralLedgerService $ledger): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $service->post($voucher, $request->attributes->get('actor'), $ledger)]);
    }

    #[OA\Post(
        path: '/vouchers/{id}/allocations',
        summary: 'Đối trừ phiếu thu với công nợ',
        description: 'Yêu cầu quyền finance.post hoặc finance.approve. Chỉ phiếu thu đã ghi sổ. Các hóa đơn phải cùng một khách hàng và không vượt số tiền chưa phân bổ.',
        tags: ['Chứng từ thu chi'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['allocations'],
                properties: [
                    new OA\Property(
                        property: 'allocations',
                        type: 'array',
                        minItems: 1,
                        items: new OA\Items(
                            required: ['receivable_id', 'amount'],
                            properties: [
                                new OA\Property(property: 'receivable_id', type: 'integer'),
                                new OA\Property(property: 'amount', type: 'number', exclusiveMinimum: 0),
                            ],
                            type: 'object'
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kết quả đối trừ'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.post hoặc finance.approve'),
            new OA\Response(response: 404, description: 'Không tìm thấy chứng từ'),
            new OA\Response(response: 422, description: 'Phiếu thu chưa ghi sổ, khác khách hàng hoặc vượt số tiền còn lại'),
        ]
    )]
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
