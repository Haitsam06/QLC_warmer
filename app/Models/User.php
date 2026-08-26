<?php

namespace App\Models;

use App\Traits\HasPostgresIdAlias;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, HasPostgresIdAlias;

    protected $fillable = [
        'role_id',
        'username',
        'email',
        'password',
        'photo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function getRoleName(): ?string
    {
        return $this->role?->role_name;
    }

    public function isAdmin(): bool
    {
        return $this->getRoleName() === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->getRoleName() === 'teacher';
    }

    public function isParents(): bool
    {
        return $this->getRoleName() === 'parents';
    }

    public function isMitra(): bool
    {
        return $this->getRoleName() === 'mitra';
    }
}