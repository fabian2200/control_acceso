<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class AccesoFestivo extends Model
{
    protected $table = 'acceso_festivos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /**
     * @return array<string, true>
     */
    public static function mapaEntre(CarbonInterface $inicio, CarbonInterface $fin): array
    {
        $fechas = static::query()
            ->whereDate('fecha', '>=', $inicio->toDateString())
            ->whereDate('fecha', '<=', $fin->toDateString())
            ->pluck('fecha');

        $mapa = [];
        foreach ($fechas as $fecha) {
            $mapa[Carbon::parse($fecha)->toDateString()] = true;
        }

        return $mapa;
    }

    public function diaLabel(): string
    {
        $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

        return $dias[$this->fecha->dayOfWeekIso - 1] ?? '';
    }
}
