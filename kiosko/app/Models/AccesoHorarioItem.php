<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccesoHorarioItem extends Model
{
    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    protected $table = 'acceso_horario_items';

    protected $guarded = [];

    public function horario(): BelongsTo
    {
        return $this->belongsTo(AccesoHorario::class, 'horario_id');
    }

    public function nombreDia(): string
    {
        return self::DIAS[$this->dia_semana] ?? 'Día '.$this->dia_semana;
    }

    public function hora(string $campo): ?string
    {
        $valor = $this->{$campo};

        if (! $valor) {
            return null;
        }

        return substr((string) $valor, 0, 5);
    }

    public function gabela(string $campo): ?int
    {
        $valor = $this->{'gabela_'.$campo} ?? null;

        return $valor === null ? null : (int) $valor;
    }

    public function esDescanso(): bool
    {
        return $this->entrada_manana === null
            && $this->salida_manana === null
            && $this->entrada_tarde === null
            && $this->salida_tarde === null;
    }

    public function resumen(): string
    {
        if ($this->esDescanso()) {
            return 'Descanso';
        }

        $partes = [];

        if ($this->hora('entrada_manana') || $this->hora('salida_manana')) {
            $partes[] = $this->tramo('entrada_manana', 'salida_manana');
        }

        if ($this->hora('entrada_tarde') || $this->hora('salida_tarde')) {
            $partes[] = $this->tramo('entrada_tarde', 'salida_tarde');
        }

        return implode(' · ', $partes);
    }

    private function tramo(string $entrada, string $salida): string
    {
        return $this->horaConGabela($entrada).'–'.$this->horaConGabela($salida);
    }

    private function horaConGabela(string $campo): string
    {
        $hora = $this->hora($campo) ?? '—';
        $gabela = $this->gabela($campo);

        if (! $gabela) {
            return $hora;
        }

        return $hora.' (+'.$gabela.')';
    }
}
