<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class VatInvoiceNumberGenerator
{
    public static function next(string $series): string
    {
        return DB::transaction(function () use ($series): string {
            $sequenceName = "vat-invoice-{$series}";
            DB::table('document_sequences')->insertOrIgnore([
                'name' => $sequenceName,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')->where('name', $sequenceName)->lockForUpdate()->firstOrFail();
            $nextValue = max((int) $sequence->next_value, self::highestIssuedNumber($series) + 1);
            DB::table('document_sequences')->where('name', $sequenceName)->update([
                'next_value' => $nextValue + 1,
                'updated_at' => now(),
            ]);

            return str_pad((string) $nextValue, 8, '0', STR_PAD_LEFT);
        });
    }

    public static function preview(string $series): string
    {
        $sequenceValue = (int) (DB::table('document_sequences')->where('name', "vat-invoice-{$series}")->value('next_value') ?? 1);

        return str_pad((string) max($sequenceValue, self::highestIssuedNumber($series) + 1), 8, '0', STR_PAD_LEFT);
    }

    private static function highestIssuedNumber(string $series): int
    {
        $highest = DB::table('vat_invoices')
            ->where('invoice_series', $series)
            ->whereBetween('invoice_number', ['00000000', '99999999'])
            ->whereRaw('LENGTH(invoice_number) = 8')
            ->max('invoice_number');

        return (int) $highest;
    }
}
