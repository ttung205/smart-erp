<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
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

    /**
     * Số chứng từ theo khách hàng: {PREFIX}-{mã khách hàng}-{ddmmyy}, trùng trong ngày thì thêm -A, -B, ... -Z, -Z1...
     * Ví dụ: QT-RE-02592-081026, QT-RE-02592-081026-A, OD-DI-00001-151026.
     *
     * @param  class-string<Model>  $model
     */
    public static function forCustomer(string $model, string $column, string $prefix, ?string $customerCode): string
    {
        $code = strtoupper(trim((string) $customerCode)) ?: 'KH';
        $base = "{$prefix}-{$code}-".now()->format('dmy');

        // Khóa các số cùng gốc trong ngày để 2 người lưu cùng lúc không lấy trùng hậu tố
        $taken = $model::query()->where($column, $base)->orWhere($column, 'like', "{$base}-%")
            ->lockForUpdate()->pluck($column)->all();
        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $letters = range('A', 'Z');
        for ($index = 0; ; $index++) {
            $candidate = $base.'-'.($letters[$index] ?? 'Z'.($index - 25));
            if (! in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }
    }
}
