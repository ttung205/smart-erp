<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRule extends Model
{
    protected $guarded = [];

    protected $casts = ['value' => 'array'];

    /** @return array<string, mixed> toàn bộ quy tắc dạng [rule_key => value] */
    public static function allRules(): array
    {
        return static::query()->pluck('value', 'rule_key')->all();
    }
}
