<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerContact extends Model
{
    protected $connection = 'catalog';
    protected $table = 'customer_contacts';

    protected $fillable = [
        'customer_id',
        'name',
        'position',
        'phone',
        'email',
        'is_primary',
        'notes',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
