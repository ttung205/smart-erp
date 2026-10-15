<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'suppliers';

    protected $fillable = [
        'code',
        'name',
        'tax_code',
        'supplier_group',
        'supplier_type',
        'phone',
        'email',
        'address',
        'contact_person',
        'contact_phone',
        'contact_email',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'bank_branch',
        'payment_terms',
        'credit_limit',
        'status',
        'notes',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
    ];
}
