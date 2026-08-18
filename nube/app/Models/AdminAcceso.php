<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminAcceso extends Authenticatable
{
    protected $table = 'admin_acceso';

    public $timestamps = false;

    protected $fillable = [
        'usuario',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
