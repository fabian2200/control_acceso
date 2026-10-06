<?php

namespace App\Services;

use App\Models\AccesoFestivo;
use App\Models\AccesoHorarioItem;
use App\Models\AccesoRegistro;
use App\Models\Empleado;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LlegadaTempranoService
{
    /**
     * @return array{
     *   anio:int,
     *   mes:int,
     *   empleado_id:?int,
     *   kpis:array{total:int,minutos:int,promedio:int,empleados:int},
     *   filas:list<array<string,mixed>>,
     *   ranking:list<array<string,mixed>>,
     *   empleados:Collection,
     *   anios:list<int>,
     *   meses:array<int,string>
     * }
     */
    public function informe(int $anio, int $mes, ?int $empleadoId): array
    {
        $anio = max(2000, $anio);
        $mes = min(12, max(1, $mes));

        $inicio = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/Bogota')->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        $empleados = Empleado::query()
            ->activos()
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->get();

        $entradas = AccesoRegistro::query()
            ->with(['empleado.cargoRel', 'horario.items', 'empleado.asignacionHorario.horario'])
            ->where('tipo', 'entrada')
            ->where('llego_temprano', '>', 0)
            ->where(function ($qb) {
                $qb->whereNull('llego_tarde')->orWhere('llego_tarde', 0);
            })
            ->whereDate('fecha', '>=', $inicio->toDateString())
            ->whereDate('fecha', '<=', $fin->toDateString())
            ->orderByDesc('fecha')
            ->orderByDesc('hora')
            ->get();

        $festivos = AccesoFestivo::mapaEntre($inicio, $fin);
        $todas = [];
        foreach ($entradas as $registro) {
            $fecha = $this->fechaCarbon($registro->fecha);
            if (isset($festivos[$fecha->toDateString()])) {
                continue;
            }
            $todas[] = $this->armarFila($registro, $fecha);
        }

        $ranking = $this->ranking($todas, $empleadoId);

        $filas = $empleadoId
            ? array_values(array_filter($todas, fn (array $fila) => (int) $fila['empleado_id'] === $empleadoId))
            : $todas;

        usort($filas, function (array $a, array $b) {
            $fa = $a['fecha'] instanceof Carbon ? $a['fecha']->timestamp : 0;
            $fb = $b['fecha'] instanceof Carbon ? $b['fecha']->timestamp : 0;
            if ($fa !== $fb) {
                return $fb <=> $fa;
            }
            $n = strcmp((string) $a['nombre'], (string) $b['nombre']);
            if ($n !== 0) {
                return $n;
            }

            return ((int) $a['jornada']) <=> ((int) $b['jornada']);
        });

        $minutos = (int) array_sum(array_column($filas, 'minutos'));
        $total = count($filas);

        return [
            'anio' => $anio,
            'mes' => $mes,
            'empleado_id' => $empleadoId,
            'kpis' => [
                'total' => $total,
                'minutos' => $minutos,
                'promedio' => $total > 0 ? (int) round($minutos / $total) : 0,
                'empleados' => count(array_unique(array_column($filas, 'empleado_id'))),
            ],
            'filas' => $filas,
            'ranking' => $ranking,
            'empleados' => $empleados,
            'anios' => $this->aniosDisponibles(),
            'meses' => LlegadaTardeService::MESES,
        ];
    }

    public function aniosDisponibles(): array
    {
        $min = AccesoRegistro::query()
            ->where('tipo', 'entrada')
            ->where('llego_temprano', '>', 0)
            ->min('fecha');
        $desde = $min ? (int) substr((string) $min, 0, 4) : (int) now('America/Bogota')->year;
        $hasta = (int) now('America/Bogota')->year;

        return range($desde, max($desde, $hasta));
    }

    /**
     * @param  list<array<string,mixed>>  $filas
     * @return list<array<string,mixed>>
     */
    private function ranking(array $filas, ?int $empleadoId): array
    {
        $porEmpleado = [];
        foreach ($filas as $fila) {
            $id = (int) $fila['empleado_id'];
            if (! isset($porEmpleado[$id])) {
                $porEmpleado[$id] = [
                    'empleado_id' => $id,
                    'nombre' => $fila['nombre'],
                    'identificacion' => $fila['identificacion'],
                    'cargo' => $fila['cargo'],
                    'veces' => 0,
                    'minutos' => 0,
                ];
            }
            $porEmpleado[$id]['veces']++;
            $porEmpleado[$id]['minutos'] += (int) $fila['minutos'];
        }

        $ranking = array_values($porEmpleado);
        usort($ranking, function (array $a, array $b) {
            if ($a['veces'] !== $b['veces']) {
                return $b['veces'] <=> $a['veces'];
            }

            return strcmp((string) $a['nombre'], (string) $b['nombre']);
        });
        $ranking = array_slice($ranking, 0, 6);

        foreach ($ranking as $i => &$fila) {
            $fila['puesto'] = $i + 1;
            $fila['seleccionado'] = $empleadoId !== null && (int) $fila['empleado_id'] === $empleadoId;
            unset($fila['minutos']);
        }
        unset($fila);

        return $ranking;
    }

    /**
     * @return array<string, mixed>
     */
    private function armarFila(AccesoRegistro $registro, Carbon $fecha): array
    {
        $empleado = $registro->empleado;
        $item = $this->itemDelDia($registro, $fecha);
        $jornada = $this->inferirJornada($registro, $item);
        $minutos = (int) $registro->llego_temprano;
        $horario = trim((string) ($registro->horario?->nombre ?? $empleado?->asignacionHorario?->horario?->nombre ?? ''));

        return [
            'id' => $registro->id,
            'empleado_id' => $registro->empleado_id,
            'nombre' => $empleado?->nombre_completo ?: 'Empleado',
            'identificacion' => $empleado?->identificacion ?? '',
            'cargo' => $empleado?->cargo_nombre ?? 'Empleado',
            'horario' => $horario !== '' ? $horario : 'Sin horario',
            'fecha' => $fecha,
            'dia_label' => $this->diaCorto($fecha),
            'jornada' => $jornada,
            'entrada' => LlegadaTardeService::horaLabel($registro->hora_esperada),
            'marco' => LlegadaTardeService::horaLabel($registro->hora),
            'minutos' => $minutos,
            'temprano_label' => LlegadaTardeService::minutosLabel($minutos).' antes',
        ];
    }

    private function itemDelDia(AccesoRegistro $registro, Carbon $fecha): ?AccesoHorarioItem
    {
        $items = $registro->horario?->items
            ?? $registro->empleado?->asignacionHorario?->horario?->items;
        if (! $items) {
            return null;
        }

        return $items->firstWhere('dia_semana', $fecha->dayOfWeekIso);
    }

    private function inferirJornada(AccesoRegistro $registro, ?AccesoHorarioItem $item): int
    {
        $esperada = $this->mins($registro->hora_esperada);
        if ($item && $esperada !== null) {
            $e1 = $this->mins($item->entrada_jornada_1);
            $e2 = $this->mins($item->entrada_jornada_2);
            if ($e2 !== null && $e1 !== null) {
                return abs($esperada - $e2) < abs($esperada - $e1) ? 2 : 1;
            }
            if ($e2 !== null && $esperada === $e2) {
                return 2;
            }
        }

        return 1;
    }

    private function fechaCarbon(mixed $fecha): Carbon
    {
        if ($fecha instanceof Carbon) {
            return $fecha->copy()->timezone('America/Bogota')->startOfDay();
        }

        return Carbon::parse((string) $fecha, 'America/Bogota')->startOfDay();
    }

    private function diaCorto(Carbon $fecha): string
    {
        $dias = [1 => 'lun', 2 => 'mar', 3 => 'mié', 4 => 'jue', 5 => 'vie', 6 => 'sáb', 7 => 'dom'];

        return ($dias[$fecha->dayOfWeekIso] ?? '').' '.$fecha->format('d/m');
    }

    private function mins(mixed $hora): ?int
    {
        if ($hora instanceof Carbon) {
            $local = $hora->copy()->timezone('America/Bogota');

            return $local->hour * 60 + $local->minute;
        }

        $digits = preg_replace('/\D+/', '', (string) $hora) ?? '';
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) <= 2) {
            $digits = str_pad($digits, 2, '0', STR_PAD_LEFT).'00';
        } elseif (strlen($digits) === 3) {
            $digits = '0'.$digits;
        }
        $digits = str_pad(substr($digits, 0, 4), 4, '0');

        return ((int) substr($digits, 0, 2)) * 60 + (int) substr($digits, 2, 2);
    }
}
