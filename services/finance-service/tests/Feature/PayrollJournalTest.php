<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use Database\Seeders\FinanceMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollJournalTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return $override + [
            'reference' => '2026-09', 'entry_date' => '2026-09-30', 'description' => 'Ghi nhận chi phí lương tháng 09/2026',
            'lines' => [
                ['debit_account_code' => '642', 'credit_account_code' => '334', 'amount' => 38636364, 'description' => 'Chi phí lương'],
                ['debit_account_code' => '334', 'credit_account_code' => '3335', 'amount' => 174318, 'description' => 'Thuế TNCN'],
            ],
        ];
    }

    public function test_journal_posts_ledger_entries_and_is_idempotent(): void
    {
        $this->seed(FinanceMasterDataSeeder::class);
        $headers = $this->jwtHeaders(['finance.post']);

        $this->postJson('/api/payroll-journals', $this->payload(), $headers)->assertCreated()
            ->assertJsonPath('data.document_number', 'LUONG-2026-09')->assertJsonPath('data.entries', 2)->assertJsonPath('data.created', 2);
        $this->postJson('/api/payroll-journals', $this->payload(), $headers)->assertCreated()->assertJsonPath('data.created', 0);

        $this->assertSame(2, GeneralLedgerEntry::where('source_type', 'PAYROLL')->count());
        $entry = GeneralLedgerEntry::where('source_key', 'PAYROLL:2026-09:0')->firstOrFail();
        $this->assertSame('642', $entry->debitAccount->code);
        $this->assertSame('334', $entry->creditAccount->code);
        $this->assertSame(38636364.0, $entry->amount);
        $this->assertSame('3335', ChartOfAccount::where('code', '3335')->value('code'), 'Có tài khoản thuế TNCN');
    }

    public function test_journal_requires_posting_permission(): void
    {
        $this->seed(FinanceMasterDataSeeder::class);

        $this->postJson('/api/payroll-journals', $this->payload(), $this->jwtHeaders(['finance.view']))->assertForbidden();
        $this->postJson('/api/payroll-journals', $this->payload())->assertUnauthorized();
        $this->assertSame(0, GeneralLedgerEntry::where('source_type', 'PAYROLL')->count());
    }

    public function test_journal_rejects_parent_accounts_zero_amounts_and_unsafe_references(): void
    {
        $this->seed(FinanceMasterDataSeeder::class);
        $headers = $this->jwtHeaders(['finance.approve']);

        $parent = $this->payload(['lines' => [['debit_account_code' => '111', 'credit_account_code' => '334', 'amount' => 1000, 'description' => 'x']]]);
        $this->postJson('/api/payroll-journals', $parent, $headers)->assertUnprocessable();
        $zero = $this->payload(['lines' => [['debit_account_code' => '642', 'credit_account_code' => '334', 'amount' => 0, 'description' => 'x']]]);
        $this->postJson('/api/payroll-journals', $zero, $headers)->assertUnprocessable();
        $this->postJson('/api/payroll-journals', $this->payload(['reference' => '2026 09/../x']), $headers)->assertUnprocessable();
    }
}
