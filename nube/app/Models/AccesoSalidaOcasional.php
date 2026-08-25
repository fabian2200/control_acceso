<?php

namespace App\Models;

use App\Support\FotoMarca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccesoSalidaOcasional extends Model
{
    protected $table = 'acceso_salidas_ocasionales';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'salida_en' => 'datetime',
            'regreso_en' => 'datetime',
            'revisada_rrhh' => 'boolean',
            'sincronizado' => 'boolean',
            'minutos_tarde' => 'integer',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(AccesoHorario::class, 'id_horario');
    }

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(Permiso::class, 'permiso_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(AccesoTerminal::class, 'terminal_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(AccesoRegistro::class, 'salida_ocasional_id');
    }

    public function fotoSalidaSrc(): ?string
    {
        return FotoMarca::src($this->foto_salida);
    }

    public function fotoRegresoSrc(): ?string
    {
        return FotoMarca::src($this->foto_regreso);
    }
}
