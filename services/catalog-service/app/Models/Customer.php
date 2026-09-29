<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'code',
        'name',
        'tax_code',
        'phone',
        'email',
        'address',
        'credit_limit',
        'status',
    ];

    public function contacts()
    {
        return $this->hasMany(CustomerContact::class, 'customer_id');
    }
}
