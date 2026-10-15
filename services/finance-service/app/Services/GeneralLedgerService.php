<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\FinanceVoucher;
use App\Models\FinanceVoucherLine;
use App\Models\GeneralLedgerEntry;
use Illuminate\Validation\ValidationException;

class GeneralLedgerService
{
    public function accountId(string $code): int
    {
        $id = ChartOfAccount::query()->where('code', $code)->where('is_postable', true)->value('id');
        if (! $id) {
            throw ValidationException::withMessages(['account' => "Không tìm thấy tài khoản hạch toán {$code}."]);
        }

        return (int) $id;
    }

    public function recordVoucher(FinanceVoucher $voucher): void
    {
        foreach ($voucher->lines as $line) {
            $this->recordVoucherLine($voucher, $line);
        }
    }

    public function recordVoucherLine(FinanceVoucher $voucher, FinanceVoucherLine $line): void
    {
        GeneralLedgerEntry::firstOrCreate(['source_key' => "VOUCHER_LINE:{$line->id}"], [
            'source_type' => 'VOUCHER', 'source_id' => $voucher->id,
            'document_number' => $voucher->voucher_number,
            'entry_date' => $voucher->accounting_date ?: $voucher->voucher_date,
            'debit_account_id' => $line->debit_account_id,
            'credit_account_id' => $line->credit_account_id,
            'amount' => $line->amount, 'currency' => $voucher->currency,
            'customer_id' => $voucher->party_type === 'CUSTOMER' ? $voucher->party_id : null,
            'description' => $line->description ?: $voucher->description,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function recordVatInvoice(array $data): void
    {
        $receivableAccount = $this->accountId('1311');
        foreach (['subtotal' => '511', 'vat_amount' => '3331'] as $field => $creditCode) {
            $amount = (float) ($data[$field] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            GeneralLedgerEntry::firstOrCreate(['source_key' => "VAT_INVOICE:{$data['vat_invoice_id']}:{$field}"], [
                'source_type' => 'VAT_INVOICE', 'source_id' => $data['vat_invoice_id'],
                'document_number' => $data['invoice_number'], 'entry_date' => $data['invoice_date'],
                'debit_account_id' => $receivableAccount,
                'credit_account_id' => $this->accountId($creditCode),
                'amount' => $amount, 'currency' => $data['currency'] ?? 'VND',
                'customer_id' => $data['customer_id'] ?? null,
                'description' => 'Hóa đơn VAT '.$data['invoice_number'],
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    public function recordVatAdjustment(array $data): void
    {
        $receivableAccount = $this->accountId('1311');
        foreach (['subtotal_delta' => ($data['adjustment_type'] === 'QUANTITY' ? '521' : '511'), 'vat_delta' => '3331'] as $field => $counterCode) {
            $delta = (float) ($data[$field] ?? 0);
            if (abs($delta) < 0.01) {
                continue;
            }
            $counterAccount = $this->accountId($counterCode);
            GeneralLedgerEntry::firstOrCreate(['source_key' => "VAT_ADJUSTMENT:{$data['adjustment_id']}:{$field}"], [
                'source_type' => 'VAT_ADJUSTMENT', 'source_id' => $data['adjustment_id'],
                'document_number' => $data['adjustment_number'], 'entry_date' => $data['adjustment_date'],
                'debit_account_id' => $delta > 0 ? $receivableAccount : $counterAccount,
                'credit_account_id' => $delta > 0 ? $counterAccount : $receivableAccount,
                'amount' => abs($delta), 'currency' => $data['currency'] ?? 'VND',
                'customer_id' => $data['customer_id'] ?? null,
                'description' => $data['reason'],
            ]);
        }
    }
}
