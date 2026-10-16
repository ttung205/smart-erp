<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'department_id',
        'line_manager_id',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function lineManager()
    {
        return $this->belongsTo(User::class, 'line_manager_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function getPermissionsAttribute()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id')
            ->values();
    }
}
