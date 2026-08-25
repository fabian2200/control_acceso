<?php

namespace App\Models;

use App\Services\LlegadaTardeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permiso extends Model
{
    protected $table = 'permisos';

    public $timestamps = false;

    protected $guarded = [];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empleado');
    }

    public function scopeActivosEnFecha($query, int $userId, ?string $fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();

        return $query
            ->where('empleado', $userId)
            ->whereRaw('LOWER(estado) = ?', ['aprobado'])
            ->where(function ($q) {
                $q->whereNull('estado_reg')
                    ->orWhereRaw('UPPER(estado_reg) = ?', ['ACTIVO']);
            })
            ->whereNull('fecha_cancelacion')
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha)
            ->orderBy('hora_inicio');
    }

    public function motivoResumen(int $max = 120): string
    {
        $texto = trim((string) $this->motivo);
        if ($texto === '') {
            $texto = 'Permiso';
        }

        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, $max - 1)).'…';
    }

    public function intervaloHoras(): ?string
    {
        $inicio = $this->horaInicioFmt();
        $fin = $this->horaFinFmt();
        if ($inicio === '--:--' || $fin === '--:--') {
            return null;
        }

        return $inicio.' – '.$fin;
    }

    public function motivoConIntervalo(int $max = 80): string
    {
        $motivo = $this->motivoResumen($max);
        $intervalo = $this->intervaloHoras();

        return $intervalo ? $motivo.' · '.$intervalo : $motivo;
    }

    public function horaInicioFmt(): string
    {
        return $this->formatearHora((string) $this->hora_inicio);
    }

    public function horaFinFmt(): string
    {
        return $this->formatearHora((string) $this->hora_fin);
    }

    public function horaFinDigitos(): string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->hora_fin) ?? '';

        if (strlen($digits) <= 2) {
            $digits = str_pad($digits, 2, '0', STR_PAD_LEFT).'00';
        } elseif (strlen($digits) === 3) {
            $digits = '0'.$digits;
        }

        return str_pad(substr($digits, 0, 4), 4, '0');
    }

    public function esMismoDia(): bool
    {
        return (string) $this->fecha_inicio === (string) $this->fecha_fin;
    }

    public function rangoFechas(): string
    {
        $inicio = $this->fecha_inicio ? date('d/m', strtotime((string) $this->fecha_inicio)) : '';
        $fin = $this->fecha_fin ? date('d/m', strtotime((string) $this->fecha_fin)) : $inicio;

        if ($inicio === '' || $this->esMismoDia()) {
            return $inicio;
        }

        return $inicio.' – '.$fin;
    }

    private function formatearHora(string $hora): string
    {
        $digits = preg_replace('/\D+/', '', $hora) ?? '';

        if ($digits === '') {
            return '--:--';
        }

        if (strlen($digits) <= 2) {
            $digits = str_pad($digits, 2, '0', STR_PAD_LEFT).'00';
        } elseif (strlen($digits) === 3) {
            $digits = '0'.$digits;
        }

        $digits = str_pad(substr($digits, 0, 4), 4, '0');

        $hhmm = substr($digits, 0, 2).':'.substr($digits, 2, 2);

        return LlegadaTardeService::horaLabel($hhmm);
    }
}
