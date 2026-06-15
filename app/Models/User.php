<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'counter_number',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
    ];

    public function toArray()
    {
        return [
            'id' => (string) $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'role' => $this->role ?? 'petugas',
            'counterNumber' => $this->counter_number !== null ? (int) $this->counter_number : null,
        ];
    }
}
