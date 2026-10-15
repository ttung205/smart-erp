<?php

namespace App\Support;

use DateTimeImmutable;

/**
 * Ngày chứng từ (báo giá, đơn bán): frontend gửi dd/mm/yyyy hoặc yyyy-mm-dd.
 * Không dùng rule `date` của Laravel vì nó đọc "05/10/2026" theo kiểu Mỹ (tháng/ngày) và từ chối "15/10/2026".
 */
class DocumentDate
{
    public const FORMATS = ['Y-m-d', 'd/m/Y'];

    /** @return list<string> */
    public static function rules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'date_format:'.implode(',', self::FORMATS)];
    }

    /** Chuẩn hóa về Y-m-d; trống thì trả về $fallback. */
    public static function normalize(?string $value, ?string $fallback = null): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }
        foreach (self::FORMATS as $format) {
            // createFromFormat của PHP trả về false (Carbon thì ném exception); so lại chuỗi để loại ngày tràn như 31/02
            $date = DateTimeImmutable::createFromFormat("!{$format}", $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $fallback;
    }
}
