<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $base = self::customerBase($prefix, $customerCode);

        // Khóa các số cùng gốc trong ngày để 2 người lưu cùng lúc không lấy trùng hậu tố
        $taken = $model::query()->where($column, $base)->orWhere($column, 'like', "{$base}-%")
            ->lockForUpdate()->pluck($column)->all();

        return self::firstFree($base, $taken);
    }

    /**
     * Cùng quy tắc với forCustomer, nhưng số được ghi vào document_number_claims trong transaction riêng
     * (phải gọi NGOÀI transaction nghiệp vụ). Lập chứng từ thất bại thì số vẫn bị chiếm, lần lập sau lấy hậu tố
     * kế tiếp. Dùng cho chứng từ mà service khác lưu theo số (Inventory giữ hàng theo số chứng từ bán hàng):
     * cấp lại số cũ sẽ khiến Inventory nhầm là chứng từ đã giữ hàng.
     */
    public static function claimForCustomer(string $scope, string $prefix, ?string $customerCode): string
    {
        $base = self::customerBase($prefix, $customerCode);

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($scope, $base): string {
                    $taken = DB::table('document_number_claims')
                        ->where('number', $base)->orWhere('number', 'like', "{$base}-%")
                        ->lockForUpdate()->pluck('number')->all();
                    $number = self::firstFree($base, $taken);
                    DB::table('document_number_claims')->insert(['scope' => $scope, 'number' => $number, 'created_at' => now()]);

                    return $number;
                });
            } catch (UniqueConstraintViolationException $e) {
                // Hai người cùng lấy số đầu tiên trong ngày: người sau thử lại và nhận hậu tố kế tiếp
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }

    private static function customerBase(string $prefix, ?string $customerCode): string
    {
        $code = strtoupper(trim((string) $customerCode)) ?: 'KH';

        return "{$prefix}-{$code}-".now()->format('dmy');
    }

    /** @param  list<string>  $taken */
    private static function firstFree(string $base, array $taken): string
    {
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
