<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $table = 'partners';

    protected $fillable = [
        'code',
        'name',
        'type',
        'tax_code',
        'email',
        'phone',
        'address',
        'credit_limit',
    ];
}
