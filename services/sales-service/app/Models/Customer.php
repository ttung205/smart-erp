<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $connection = 'catalog';
    protected $table = 'customers';

    protected $fillable = [
        'code',
        'name',
        'customer_type',
        'tax_code',
        'phone',
        'email',
        'address',
        'credit_limit',
        'status',
        'created_by',
    ];

    public function contacts()
    {
        return $this->hasMany(CustomerContact::class, 'customer_id');
    }
}
