<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccesoRegistro extends Model
{
    protected $table = 'acceso_registros';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'registrado_en' => 'datetime',
            'sincronizado' => 'boolean',
            'llego_tarde' => 'integer',
            'llego_temprano' => 'integer',
            'salio_temprano' => 'integer',
            'salio_tarde' => 'integer',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(AccesoTerminal::class, 'terminal_id');
    }

    public function salidaOcasional(): BelongsTo
    {
        return $this->belongsTo(AccesoSalidaOcasional::class, 'salida_ocasional_id');
    }
}
