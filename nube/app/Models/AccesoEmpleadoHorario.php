<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccesoEmpleadoHorario extends Model
{
    protected $table = 'acceso_empleado_horarios';

    protected $guarded = [];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(AccesoHorario::class, 'horario_id');
    }
}
