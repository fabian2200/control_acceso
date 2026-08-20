<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccesoNovedad extends Model
{
    protected $table = 'acceso_novedades';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'jornada' => 'integer',
            'aprobada' => 'integer',
            'sincronizado' => 'boolean',
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

    public function estadoEtiqueta(): string
    {
        return match ($this->aprobada) {
            1 => 'Aprobada',
            0 => 'Rechazada',
            default => 'Pendiente',
        };
    }
}
