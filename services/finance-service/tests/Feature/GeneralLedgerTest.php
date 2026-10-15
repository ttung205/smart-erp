<?php

namespace Tests\Feature;

use App\Models\CustomerReceivable;
use Database\Seeders\FinanceMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_cash_voucher_appears_in_general_ledger(): void
    {
        $this->seed(FinanceMasterDataSeeder::class);
        $headers = $this->jwtHeaders(['finance.view', 'finance.approve']);
        $fundId = $this->getJson('/api/master-data', $headers)->json('data.cash_funds.0.id');
        $voucher = $this->postJson('/api/vouchers', [
            'voucher_type' => 'CASH_RECEIPT', 'channel' => 'CASH',
            'voucher_date' => '2026-10-15', 'party_type' => 'CUSTOMER',
            'party_id' => 7, 'party_name' => 'Khách hàng A',
            'description' => 'Thu tiền khách hàng', 'cash_fund_id' => $fundId,
            'total_amount' => 300, 'counter_account_code' => '1311',
        ], $headers)->assertCreated()->json('data');
        $this->postJson("/api/vouchers/{$voucher['id']}/post", [], $headers)->assertOk();
        $this->assertDatabaseHas('general_ledger_entries', [
            'source_key' => 'VOUCHER_LINE:'.$voucher['lines'][0]['id'], 'amount' => 300,
        ]);
        $this->getJson('/api/general-ledger?account_id='.$voucher['lines'][0]['debit_account_id'], $headers)
            ->assertOk()->assertJsonPath('summary.total_debit', 300);
    }

    public function test_vat_invoice_and_adjustment_post_to_ledger_and_receivable_once(): void
    {
        $this->seed(FinanceMasterDataSeeder::class);
        $headers = $this->jwtHeaders(['finance.view', 'finance.approve']);
        $receivable = $this->postJson('/api/customer-receivables/sync', [
            'vat_invoice_id' => 42, 'customer_id' => 7, 'customer_name' => 'Khách hàng A',
            'invoice_number' => '00001243', 'invoice_date' => '2026-10-15',
            'subtotal' => 1000, 'vat_amount' => 100, 'total_amount' => 1100,
        ], $headers)->assertCreated()->json('data');
        $payload = [
            'adjustment_id' => 98, 'original_vat_invoice_id' => 42,
            'adjustment_number' => 'ADJ-2026-000098', 'adjustment_date' => '2026-10-15',
            'reason' => 'Giảm đơn giá', 'adjustment_type' => 'PRICE',
            'subtotal_delta' => -200, 'vat_delta' => -20,
            'total_delta' => -220, 'currency' => 'VND', 'customer_id' => 7,
        ];
        $this->postJson('/api/invoice-adjustments/sync', $payload, $headers)->assertOk()
            ->assertJsonPath('data.original_amount', 880)
            ->assertJsonPath('data.outstanding_amount', 880);
        $this->postJson('/api/invoice-adjustments/sync', $payload, $headers)->assertOk();
        $this->assertDatabaseCount('receivable_adjustments', 1);
        $this->assertDatabaseCount('general_ledger_entries', 4);

        $accountId = $receivable['account_id'];
        $this->getJson("/api/general-ledger?account_id={$accountId}", $headers)->assertOk()
            ->assertJsonPath('summary.total_debit', 1100)
            ->assertJsonPath('summary.total_credit', 220)
            ->assertJsonPath('summary.closing_balance', 880);

        $this->postJson('/api/customer-receivables/sync', [
            'vat_invoice_id' => 42, 'customer_id' => 7, 'customer_name' => 'Khách hàng A',
            'invoice_number' => '00001243', 'invoice_date' => '2026-10-15',
            'subtotal' => 1000, 'vat_amount' => 100, 'total_amount' => 1100,
        ], $headers)->assertCreated();
        $this->assertSame(880.0, CustomerReceivable::find($receivable['id'])->original_amount);
    }

    public function test_decrease_after_payment_is_reported_as_customer_credit(): void
    {
        $this->seed(FinanceMasterDataSeeder::class);
        $headers = $this->jwtHeaders(['finance.view', 'finance.approve']);
        $receivable = $this->postJson('/api/customer-receivables/sync', [
            'vat_invoice_id' => 43, 'customer_name' => 'Customer B',
            'invoice_number' => '00001244', 'invoice_date' => '2026-10-15',
            'subtotal' => 1000, 'vat_amount' => 100, 'total_amount' => 1100,
        ], $headers)->assertCreated()->json('data');
        CustomerReceivable::findOrFail($receivable['id'])->update([
            'paid_amount' => 1100, 'outstanding_amount' => 0, 'status' => 'PAID',
        ]);
        $this->postJson('/api/invoice-adjustments/sync', [
            'adjustment_id' => 99, 'original_vat_invoice_id' => 43,
            'adjustment_number' => 'ADJ-2026-000099', 'adjustment_date' => '2026-10-15',
            'reason' => 'Returned goods', 'adjustment_type' => 'QUANTITY',
            'subtotal_delta' => -200, 'vat_delta' => -20,
            'total_delta' => -220, 'currency' => 'VND',
        ], $headers)->assertOk()->assertJsonPath('data.effective_status', 'CREDIT');
        $this->getJson('/api/customer-receivables?status=CREDIT', $headers)->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('summary.total_outstanding', 0)
            ->assertJsonPath('summary.total_credit', 220)
            ->assertJsonPath('summary.overdue_outstanding', 0);
    }
}
