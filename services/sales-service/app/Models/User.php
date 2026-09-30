<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $connection = 'identity';
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'department_id',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];
}
