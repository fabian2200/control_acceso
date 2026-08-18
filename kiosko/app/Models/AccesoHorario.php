<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccesoHorario extends Model
{
    protected $table = 'acceso_horarios';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccesoHorarioItem::class, 'horario_id')->orderBy('dia_semana');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AccesoEmpleadoHorario::class, 'horario_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(AccesoRegistro::class, 'id_horario');
    }

    public function salidasOcasionales(): HasMany
    {
        return $this->hasMany(AccesoSalidaOcasional::class, 'id_horario');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('nombre');
    }

    public function itemDelDia(int $dia): ?AccesoHorarioItem
    {
        return $this->items->firstWhere('dia_semana', $dia);
    }
}
