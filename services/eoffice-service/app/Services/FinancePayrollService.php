<?php

namespace App\Services;

use App\Models\PayrollPeriod;
use App\Support\Outbox;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Chi lương và hạch toán sang finance-service bằng chính JWT của kế toán thực hiện chi (finance tự kiểm tra quyền).
 * Mọi bước idempotent (reference / idempotency_key theo kỳ) nên lỗi giữa chừng có thể bấm chi lại an toàn.
 *
 * 1. Bút toán ghi nhận chi phí: Nợ 642 / Có 334; giữ lại bảo hiểm (338), thuế TNCN (3335), phạt (711), khấu trừ khác (138).
 * 2. Phiếu chi ngân hàng: Nợ 334 / Có 1121 bằng tổng lương thực nhận, rồi ghi sổ.
 * Chưa hạch toán phần bảo hiểm do doanh nghiệp đóng thêm và chưa tách chi phí theo bộ phận.
 */
class FinancePayrollService
{
    public function __construct(private readonly PayrollService $payroll, private readonly ApprovalService $approvals) {}

    public function pay(PayrollPeriod $period, object $actor, string $token, int $bankAccountId): PayrollPeriod
    {
        if ($period->status === PayrollPeriod::PAID) {
            return $period;
        }
        if ($period->status !== PayrollPeriod::APPROVED) {
            throw ValidationException::withMessages(['period' => 'Chỉ chi lương cho bảng lương đã được duyệt.']);
        }

        $slips = $period->slips();
        $totals = [
            'gross' => (float) $slips->sum('gross_income'), 'insurance' => (float) $slips->sum('insurance_amount'),
            'pit' => (float) $slips->sum('pit_amount'), 'penalty' => (float) $slips->sum('penalty_amount'),
            'deduction' => (float) $slips->sum('deduction_amount'), 'net' => (float) $slips->sum('net_pay'),
        ];
        if ($totals['net'] <= 0) {
            throw ValidationException::withMessages(['period' => 'Tổng lương thực nhận bằng 0, không có gì để chi.']);
        }

        $client = $this->client($token);
        $this->postJournal($client, $period, $totals);
        $period->update(['finance_status' => 'JOURNAL_POSTED', 'finance_error' => null]);
        $voucherNumber = $this->payAndPost($client, $period, $totals['net'], $bankAccountId);

        return DB::transaction(function () use ($period, $actor, $voucherNumber) {
            $period->update([
                'status' => PayrollPeriod::PAID, 'paid_at' => now(), 'paid_by' => $actor->id,
                'finance_status' => 'POSTED', 'finance_reference' => $voucherNumber, 'finance_error' => null,
            ]);
            $this->approvals->record($period, $actor, 'PAID', "Phiếu chi {$voucherNumber}");
            $this->payroll->recordEvent('PayrollPaid', 'paid', $period, [
                'actor' => Outbox::actor($actor), 'recipient_ids' => $period->slips()->pluck('user_id')->all(),
            ]);

            return $period->fresh();
        });
    }

    /** @param  array<string, float>  $totals */
    private function postJournal(PendingRequest $client, PayrollPeriod $period, array $totals): void
    {
        $label = $period->label();
        $candidates = [
            ['642', '334', $totals['gross'], "Chi phí lương tháng {$label}"],
            ['334', '338', $totals['insurance'], "Bảo hiểm phần người lao động tháng {$label}"],
            ['334', '3335', $totals['pit'], "Thuế TNCN khấu trừ tháng {$label}"],
            ['334', '711', $totals['penalty'], "Phạt đi trễ/về sớm tháng {$label}"],
            ['334', '138', $totals['deduction'], "Khấu trừ khác từ lương tháng {$label}"],
        ];
        $lines = collect($candidates)->filter(fn (array $line) => $line[2] > 0)
            ->map(fn (array $line) => ['debit_account_code' => $line[0], 'credit_account_code' => $line[1], 'amount' => $line[2], 'description' => $line[3]])
            ->values()->all();

        $this->send($period, fn () => $client->post('/api/payroll-journals', [
            'reference' => sprintf('%d-%02d', $period->year, $period->month),
            'entry_date' => CarbonImmutable::create($period->year, $period->month, 1)->endOfMonth()->toDateString(),
            'description' => "Ghi nhận chi phí lương tháng {$label}",
            'lines' => $lines,
        ]));
    }

    private function payAndPost(PendingRequest $client, PayrollPeriod $period, float $net, int $bankAccountId): string
    {
        $label = $period->label();
        $created = $this->send($period, fn () => $client->post('/api/vouchers', [
            'idempotency_key' => Uuid::uuid5(Uuid::NAMESPACE_URL, "smart-erp/payroll/{$period->id}/payment")->toString(),
            'voucher_type' => 'BANK_PAYMENT', 'channel' => 'BANK', 'voucher_date' => today()->toDateString(),
            'party_type' => 'OTHER', 'party_name' => "Người lao động - lương tháng {$label}",
            'description' => "Chi lương tháng {$label}", 'bank_account_id' => $bankAccountId, 'total_amount' => $net,
            'lines' => [['description' => "Chi lương tháng {$label}", 'debit_account_code' => '334', 'credit_account_code' => '1121', 'amount' => $net]],
        ]));
        $voucher = (array) $created->json('data');
        if (($voucher['status'] ?? null) !== 'POSTED') {
            $voucher = (array) $this->send($period, fn () => $client->post('/api/vouchers/'.$voucher['id'].'/post'))->json('data');
        }

        return (string) ($voucher['voucher_number'] ?? '');
    }

    private function client(string $token): PendingRequest
    {
        return Http::baseUrl((string) config('services.finance.url'))->withToken($token)->acceptJson()->timeout(20);
    }

    /** Gọi finance; lỗi thì ghi lại vào kỳ lương (để kế toán thấy lý do và chi lại) rồi báo cho người dùng. */
    private function send(PayrollPeriod $period, callable $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException) {
            $this->fail($period, 'Không kết nối được finance-service.');
        }
        if (! $response->successful()) {
            $detail = $response->json('error.message') ?? $response->json('message') ?? ('Mã lỗi '.$response->status());
            $this->fail($period, $response->status() === 403
                ? 'Tài khoản của bạn chưa có quyền ghi sổ ở finance-service (cần finance.post hoặc finance.approve).'
                : "finance-service từ chối: {$detail}");
        }

        return $response;
    }

    private function fail(PayrollPeriod $period, string $message): never
    {
        $period->update(['finance_status' => 'FAILED', 'finance_error' => $message]);

        throw ValidationException::withMessages(['finance' => $message]);
    }
}
