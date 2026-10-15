<?php

namespace Tests\Feature;

use Database\Seeders\FinanceMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receipt_can_offset_multiple_receivables(): void
    {
        $this->seed(FinanceMasterDataSeeder::class);
        $headers = $this->jwtHeaders(['finance.view', 'finance.approve']);
        $receivable = $this->withHeaders($headers)->postJson('/api/customer-receivables/sync', [
            'vat_invoice_id' => 10, 'customer_id' => 5, 'customer_name' => 'Công ty Long Sơn',
            'invoice_number' => '00000010', 'invoice_date' => '2026-10-15', 'due_date' => '2026-11-14',
            'currency' => 'VND', 'total_amount' => 1_100_000,
        ])->assertCreated()->json('data');
        $secondReceivable = $this->withHeaders($headers)->postJson('/api/customer-receivables/sync', [
            'vat_invoice_id' => 11, 'customer_id' => 5, 'customer_name' => 'Công ty Long Sơn',
            'invoice_number' => '00000011', 'invoice_date' => '2026-10-16', 'due_date' => '2026-11-15',
            'currency' => 'VND', 'total_amount' => 500_000,
        ])->assertCreated()->json('data');

        $fundId = $this->withHeaders($headers)->getJson('/api/master-data')->assertOk()->json('data.cash_funds.0.id');
        $voucher = $this->withHeaders($headers)->postJson('/api/vouchers', [
            'voucher_type' => 'CASH_RECEIPT', 'channel' => 'CASH', 'voucher_date' => '2026-10-15',
            'party_type' => 'CUSTOMER', 'party_id' => 5, 'party_name' => 'Công ty Long Sơn',
            'description' => 'Thu tiền hóa đơn 00000010', 'cash_fund_id' => $fundId,
            'total_amount' => 600_000, 'counter_account_code' => '1311',
            'allocations' => [
                ['receivable_id' => $receivable['id'], 'amount' => 400_000],
                ['receivable_id' => $secondReceivable['id'], 'amount' => 200_000],
            ],
        ])->assertCreated()->json('data');

        $this->withHeaders($headers)->postJson("/api/vouchers/{$voucher['id']}/post")->assertOk()->assertJsonPath('data.status', 'POSTED');

        $this->assertDatabaseHas('customer_receivables', ['id' => $receivable['id'], 'paid_amount' => 400_000, 'outstanding_amount' => 700_000, 'status' => 'PARTIALLY_PAID']);
        $this->assertDatabaseHas('customer_receivables', ['id' => $secondReceivable['id'], 'paid_amount' => 200_000, 'outstanding_amount' => 300_000, 'status' => 'PARTIALLY_PAID']);
        $this->withHeaders($headers)->postJson('/api/customer-receivables/sync', [
            'vat_invoice_id' => 10, 'customer_id' => 5, 'customer_name' => 'Công ty Long Sơn',
            'invoice_number' => '00000010', 'invoice_date' => '2026-10-15', 'due_date' => '2026-11-14',
            'currency' => 'VND', 'total_amount' => 1_100_000,
        ])->assertCreated();
        $this->assertDatabaseHas('customer_receivables', ['id' => $receivable['id'], 'paid_amount' => 400_000, 'outstanding_amount' => 700_000]);
        $this->withHeaders($headers)->getJson('/api/customer-receivables')->assertOk()->assertJsonPath('summary.total_outstanding', 1_000_000);
    }

    public function test_internal_bank_transfer_updates_both_bank_books(): void
    {
        $this->seed(FinanceMasterDataSeeder::class);
        $headers = $this->jwtHeaders(['finance.view', 'finance.approve']);
        $banks = $this->withHeaders($headers)->getJson('/api/master-data')->assertOk()->json('data.bank_accounts');
        $voucher = $this->withHeaders($headers)->postJson('/api/vouchers', [
            'voucher_type' => 'INTERNAL_TRANSFER', 'channel' => 'BANK', 'voucher_date' => '2026-10-15',
            'description' => 'Chuyển tiền giữa hai ngân hàng', 'bank_account_id' => $banks[0]['id'],
            'destination_bank_account_id' => $banks[1]['id'], 'total_amount' => 2_000_000,
        ])->assertCreated()->json('data');

        $this->withHeaders($headers)->postJson("/api/vouchers/{$voucher['id']}/post")->assertOk();
        $this->withHeaders($headers)->getJson('/api/bank-book?bank_account_id='.$banks[0]['id'])->assertOk()->assertJsonPath('summary.closing_balance', -2_000_000);
        $this->withHeaders($headers)->getJson('/api/bank-book?bank_account_id='.$banks[1]['id'])->assertOk()->assertJsonPath('summary.closing_balance', 2_000_000);
    }

    public function test_cash_receipt_accepts_document_details_and_multiple_accounting_lines(): void
    {
        $this->seed(FinanceMasterDataSeeder::class);
        $headers = $this->jwtHeaders(['finance.view', 'finance.approve']);
        $fundId = $this->withHeaders($headers)->getJson('/api/master-data')->assertOk()->json('data.cash_funds.0.id');

        $voucher = $this->withHeaders($headers)->postJson('/api/vouchers', [
            'voucher_type' => 'CASH_RECEIPT', 'channel' => 'CASH', 'voucher_date' => '2026-10-15',
            'accounting_date' => '2026-10-15', 'party_type' => 'CUSTOMER', 'party_id' => 5,
            'party_code' => 'KH-00005', 'party_name' => 'Công ty Long Sơn', 'party_address' => 'Hà Nội',
            'contact_name' => 'Nguyễn Văn An', 'reason_code' => 'CUSTOMER_RECEIPT', 'employee_name' => 'Lê Ngọc Anh',
            'description' => 'Thu tiền khách hàng', 'cash_fund_id' => $fundId, 'total_amount' => 8_000_000,
            'currency' => 'USD', 'exchange_rate' => 25_450.25,
            'lines' => [
                ['description' => 'Tiền chưa xác định hóa đơn', 'debit_account_code' => '1111', 'credit_account_code' => '138', 'amount' => 5_000_000],
                ['description' => 'Thu nhập khác', 'debit_account_code' => '1111', 'credit_account_code' => '711', 'amount' => 3_000_000],
            ],
        ])->assertCreated()->json('data');

        $this->assertCount(2, $voucher['lines']);
        $this->assertSame('DRAFT', $voucher['status']);
        $this->assertDatabaseHas('finance_vouchers', [
            'id' => $voucher['id'], 'party_code' => 'KH-00005', 'contact_name' => 'Nguyễn Văn An',
            'reason_code' => 'CUSTOMER_RECEIPT', 'total_amount' => 8_000_000, 'currency' => 'USD', 'exchange_rate' => 25_450.25,
        ]);
        $this->withHeaders($headers)->getJson("/api/cash-book?cash_fund_id={$fundId}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->withHeaders($headers)->postJson("/api/vouchers/{$voucher['id']}/post")->assertOk()->assertJsonPath('data.status', 'POSTED');
    }

    public function test_posted_receipt_can_be_allocated_to_invoices_later(): void
    {
        $this->seed(FinanceMasterDataSeeder::class);
        $headers = $this->jwtHeaders(['finance.view', 'finance.approve']);
        $receivables = [];
        foreach ([4_000_000, 3_000_000, 3_000_000] as $index => $amount) {
            $receivables[] = $this->withHeaders($headers)->postJson('/api/customer-receivables/sync', [
                'vat_invoice_id' => 100 + $index, 'customer_id' => 20, 'customer_name' => 'Khách hàng A',
                'invoice_number' => 'HD-'.($index + 1), 'invoice_date' => '2026-10-15',
                'currency' => 'VND', 'total_amount' => $amount,
            ])->assertCreated()->json('data');
        }
        $bankId = $this->withHeaders($headers)->getJson('/api/master-data')->json('data.bank_accounts.0.id');
        $voucher = $this->withHeaders($headers)->postJson('/api/vouchers', [
            'voucher_type' => 'BANK_RECEIPT', 'channel' => 'BANK', 'voucher_date' => '2026-10-15',
            'party_type' => 'OTHER', 'party_name' => 'Khách hàng chuyển khoản', 'description' => 'Tiền về chưa rõ hóa đơn',
            'bank_account_id' => $bankId, 'total_amount' => 8_000_000, 'counter_account_code' => '138',
        ])->assertCreated()->json('data');
        $this->withHeaders($headers)->postJson("/api/vouchers/{$voucher['id']}/post")->assertOk()->assertJsonPath('data.unallocated_amount', 8_000_000);

        $this->withHeaders($headers)->postJson("/api/vouchers/{$voucher['id']}/allocations", [
            'allocations' => [
                ['receivable_id' => $receivables[0]['id'], 'amount' => 4_000_000],
                ['receivable_id' => $receivables[1]['id'], 'amount' => 3_000_000],
                ['receivable_id' => $receivables[2]['id'], 'amount' => 1_000_000],
            ],
        ])->assertOk()->assertJsonPath('data.unallocated_amount', 0);

        $this->assertDatabaseHas('customer_receivables', ['id' => $receivables[0]['id'], 'outstanding_amount' => 0, 'status' => 'PAID']);
        $this->assertDatabaseHas('customer_receivables', ['id' => $receivables[1]['id'], 'outstanding_amount' => 0, 'status' => 'PAID']);
        $this->assertDatabaseHas('customer_receivables', ['id' => $receivables[2]['id'], 'outstanding_amount' => 2_000_000, 'status' => 'PARTIALLY_PAID']);
    }
}
