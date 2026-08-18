<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccesoTerminal extends Model
{
    protected $table = 'acceso_terminales';

    protected $guarded = [];

    protected $hidden = [
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
