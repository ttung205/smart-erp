<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * Nhận bút toán ghi nhận chi phí lương từ eoffice-service (Nợ 642 / Có 334, các khoản giữ lại...).
 * Idempotent theo `reference`: gọi lại cùng kỳ không ghi trùng sổ cái.
 */
class PayrollJournalController extends Controller
{
    #[OA\Post(
        path: '/payroll-journals',
        summary: 'Ghi sổ bút toán chi phí lương',
        description: 'Yêu cầu quyền finance.post hoặc finance.approve. eoffice-service gọi khi chốt bảng lương. Idempotent theo reference: gọi lại cùng kỳ không ghi trùng sổ cái. Chỉ hạch toán vào tài khoản chi tiết.',
        tags: ['Sổ cái'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reference', 'entry_date', 'description', 'lines'],
                properties: [
                    new OA\Property(property: 'reference', type: 'string', maxLength: 60, pattern: '^[A-Za-z0-9._-]+$', example: 'PR-2026-10'),
                    new OA\Property(property: 'entry_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 255),
                    new OA\Property(
                        property: 'lines',
                        type: 'array',
                        maxItems: 20,
                        minItems: 1,
                        items: new OA\Items(
                            required: ['description', 'debit_account_code', 'credit_account_code', 'amount'],
                            properties: [
                                new OA\Property(property: 'description', type: 'string', maxLength: 255),
                                new OA\Property(property: 'debit_account_code', type: 'string', example: '642'),
                                new OA\Property(property: 'credit_account_code', type: 'string', example: '334'),
                                new OA\Property(property: 'amount', type: 'number', exclusiveMinimum: 0),
                            ],
                            type: 'object'
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Số chứng từ, số bút toán và số bút toán mới được tạo'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.post hoặc finance.approve'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED, hoặc hạch toán vào tài khoản tổng hợp'),
        ]
    )]
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
