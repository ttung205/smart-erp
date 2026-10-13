<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DocumentNumberGenerator
{
    public static function next(string $sequence, string $prefix = '', int $digits = 4): string
    {
        DB::table('document_sequences')->insertOrIgnore([
            'name' => $sequence,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('document_sequences')->where('name', $sequence)->lockForUpdate()->firstOrFail();
        $value = (int) $row->next_value;
        DB::table('document_sequences')->where('name', $sequence)->update([
            'next_value' => $value + 1,
            'updated_at' => now(),
        ]);

        return $prefix.str_pad((string) $value, $digits, '0', STR_PAD_LEFT);
    }
}
