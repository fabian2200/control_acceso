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
        return $this->entrada_jornada_1 === null
            && $this->salida_jornada_1 === null
            && $this->entrada_jornada_2 === null
            && $this->salida_jornada_2 === null;
    }

    public function resumen(): string
    {
        if ($this->esDescanso()) {
            return 'Descanso';
        }

        $partes = [];

        if ($this->hora('entrada_jornada_1') || $this->hora('salida_jornada_1')) {
            $partes[] = 'J1 '.$this->tramo('entrada_jornada_1', 'salida_jornada_1');
        }

        if ($this->hora('entrada_jornada_2') || $this->hora('salida_jornada_2')) {
            $partes[] = 'J2 '.$this->tramo('entrada_jornada_2', 'salida_jornada_2');
        }

        return implode(' · ', $partes);
    }

    private function tramo(string $entrada, string $salida): string
    {
        return $this->horaConGabela($entrada).'–'.$this->horaConGabela($salida);
    }

    private function horaConGabela(string $campo): string
    {
        $hora = $this->hora($campo);
        $texto = \App\Services\LlegadaTardeService::horaLabel($hora);
        $gabela = $this->gabela($campo);

        if (! $gabela) {
            return $texto;
        }

        return $texto.' (+'.$gabela.')';
    }
}
