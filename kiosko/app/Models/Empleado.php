<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Empleado extends Model
{
    protected $table = 'empleados';

    public $timestamps = false;

    protected $guarded = [];

    public function cargoRel(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cargo');
    }

    public function departamentoRel(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento');
    }

    public function empresaRel(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(AccesoRegistro::class, 'empleado_id');
    }

    public function salidasOcasionales(): HasMany
    {
        return $this->hasMany(AccesoSalidaOcasional::class, 'empleado_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'empleado');
    }

    public function asignacionHorario(): HasOne
    {
        return $this->hasOne(AccesoEmpleadoHorario::class, 'empleado_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim(($this->nombres ?? '').' '.($this->apellidos ?? ''));
    }

    public function getPrimerNombreAttribute(): string
    {
        $nombres = trim((string) $this->nombres);

        return $nombres === '' ? 'empleado' : explode(' ', $nombres)[0];
    }

    public function getCargoNombreAttribute(): string
    {
        return $this->cargoRel?->nombre ?? 'Empleado';
    }

    public function fotoSrc(): ?string
    {
        $foto = $this->foto;

        if (! is_string($foto) || $foto === '') {
            return null;
        }

        if (str_starts_with($foto, 'data:image')) {
            return $foto;
        }

        if (str_starts_with($foto, 'http') || str_starts_with($foto, '/')) {
            return $foto;
        }

        return asset($foto);
    }

    public function scopeActivos($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('estado')
                ->orWhereRaw('LOWER(estado) = ?', ['activo']);
        });
    }
}
