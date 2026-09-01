<?php

namespace App\Services;

use App\Models\AccesoFestivo;
use App\Models\AccesoSalidaOcasional;
use App\Models\Empleado;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalidaOcasionalInformeService
{
    /**
     * @return array{
     *   anio:int,
     *   mes:int,
     *   empleado_id:?int,
     *   motivo:string,
     *   kpis:array{total:int,minutos:int,empleados:int},
     *   filas:list<array<string,mixed>>,
     *   empleados:Collection,
     *   anios:list<int>,
     *   meses:array<int,string>
     * }
     */
    public function informe(int $anio, int $mes, ?int $empleadoId, string $motivo): array
    {
        $anio = max(2000, $anio);
        $mes = min(12, max(1, $mes));
        $motivo = in_array($motivo, ['todos', 'permiso', 'diligencia', 'ocasional'], true) ? $motivo : 'todos';

        $inicio = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/Bogota')->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $ahora = now('America/Bogota');

        $empleados = Empleado::query()
            ->activos()
            ->with(['cargoRel', 'asignacionHorario.horario'])
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->get();

        $ocasionales = AccesoSalidaOcasional::query()
            ->with(['empleado.cargoRel', 'empleado.asignacionHorario.horario', 'horario', 'permiso'])
            ->whereBetween('salida_en', [$inicio, $fin])
            ->where('minutos_tarde', '>', 0)
            ->when($empleadoId, fn ($qb) => $qb->where('empleado_id', $empleadoId))
            ->orderByDesc('salida_en')
            ->get();

        $festivos = AccesoFestivo::mapaEntre($inicio, $fin);

        $filas = [];
        foreach ($ocasionales as $ocasional) {
            $fila = $this->armarFila($ocasional, $ahora);
            if (isset($festivos[$fila['fecha']->toDateString()])) {
                continue;
            }
            if ($fila['cumplimiento'] !== 'tarde') {
                continue;
            }
            if ($motivo !== 'todos' && $fila['motivo_tipo'] !== $motivo) {
                continue;
            }
            $filas[] = $fila;
        }

        $minutos = (int) array_sum(array_column($filas, 'minutos'));
        $empleadosUnicos = count(array_unique(array_column($filas, 'empleado_id')));

        return [
            'anio' => $anio,
            'mes' => $mes,
            'empleado_id' => $empleadoId,
            'motivo' => $motivo,
            'kpis' => [
                'total' => count($filas),
                'minutos' => $minutos,
                'empleados' => $empleadosUnicos,
            ],
            'filas' => $filas,
            'empleados' => $empleados,
            'anios' => $this->aniosDisponibles(),
            'meses' => LlegadaTardeService::MESES,
        ];
    }

    public function aniosDisponibles(): array
    {
        $min = AccesoSalidaOcasional::query()->min('salida_en');
        $desde = $min ? (int) substr((string) $min, 0, 4) : (int) now('America/Bogota')->year;
        $hasta = (int) now('America/Bogota')->year;

        return range($desde, max($desde, $hasta));
    }

    /**
     * @return array<string, mixed>
     */
    private function armarFila(AccesoSalidaOcasional $ocasional, Carbon $ahora): array
    {
        $empleado = $ocasional->empleado;
        $salida = $ocasional->salida_en
            ? Carbon::parse($ocasional->salida_en)->timezone('America/Bogota')
            : $ahora->copy();
        $esperadoEn = $this->esperadoEn($salida, $ocasional->hora_regreso_esperada);
        $regreso = $ocasional->regreso_en
            ? Carbon::parse($ocasional->regreso_en)->timezone('America/Bogota')
            : null;

        $motivoTipo = $this->motivoTipo($ocasional);
        $intervalo = $motivoTipo === 'permiso' ? $ocasional->permiso?->intervaloHoras() : null;
        $motivo = match ($motivoTipo) {
            'permiso' => $ocasional->permiso?->motivoConIntervalo(80) ?: ($ocasional->motivo_texto ?: 'Permiso'),
            'diligencia' => 'Diligencia empresarial',
            default => (trim((string) $ocasional->motivo_texto) !== '' ? trim((string) $ocasional->motivo_texto) : 'Salida ocasional'),
        };

        $estadoDb = (string) $ocasional->estado;
        $minutos = (int) $ocasional->minutos_tarde;
        $cumplimiento = $this->cumplimiento($estadoDb, $minutos, $esperadoEn, $regreso, $ahora);

        $autoriza = trim((string) $ocasional->autorizado_por);
        $horario = trim((string) ($ocasional->horario?->nombre ?? $empleado?->asignacionHorario?->horario?->nombre ?? ''));

        return [
            'id' => $ocasional->id,
            'empleado_id' => $ocasional->empleado_id,
            'nombre' => $empleado?->nombre_completo ?: 'Empleado',
            'identificacion' => $empleado?->identificacion ?? '',
            'cargo' => $empleado?->cargo_nombre ?? 'Empleado',
            'horario' => $horario !== '' ? $horario : 'Sin horario',
            'fecha' => $salida->copy()->startOfDay(),
            'dia_label' => $this->diaCorto($salida),
            'salio' => LlegadaTardeService::horaLabel($salida),
            'esperado' => LlegadaTardeService::horaLabel($ocasional->hora_regreso_esperada),
            'regreso' => $regreso ? LlegadaTardeService::horaLabel($regreso) : '—',
            'minutos' => $minutos,
            'cumplimiento' => $cumplimiento,
            'cumplimiento_label' => match ($cumplimiento) {
                'a_tiempo' => 'A tiempo',
                'tarde' => LlegadaTardeService::minutosLabel($minutos).' tarde',
                'abierta' => 'Abierta',
                default => 'Sin regreso',
            },
            'motivo_tipo' => $motivoTipo,
            'motivo_label' => match ($motivoTipo) {
                'permiso' => 'Permiso',
                'diligencia' => 'Diligencia',
                default => 'Ocasional',
            },
            'motivo' => $motivo,
            'permiso_intervalo' => $intervalo,
            'autoriza' => $autoriza,
            'estado' => $estadoDb,
            'titulo_detalle' => match ($cumplimiento) {
                'a_tiempo' => 'CUMPLIÓ EL REGRESO',
                'tarde' => 'REGRESÓ TARDE',
                'abierta' => 'SALIDA ABIERTA',
                default => 'SIN REGRESO',
            },
            'mensaje' => $this->mensaje($cumplimiento, $motivo, $autoriza, $minutos, $esperadoEn),
            'pie' => match ($cumplimiento) {
                'a_tiempo' => 'El empleado regresó dentro de la hora pactada, o la salida se cerró según el caso de jornada.',
                'tarde' => 'El empleado regresó después de la hora esperada.',
                'abierta' => 'La salida sigue abierta; aún no vence la hora de regreso.',
                default => 'La hora de regreso ya pasó y la salida no se ha cerrado en el kiosko.',
            },
        ];
    }

    private function cumplimiento(string $estado, int $minutos, Carbon $esperado, ?Carbon $regreso, Carbon $ahora): string
    {
        if ($estado === 'cerrada' || $regreso !== null) {
            return $minutos > 0 ? 'tarde' : 'a_tiempo';
        }
        if ($estado === 'vencida' || $ahora->gt($esperado)) {
            return 'vencida';
        }

        return 'abierta';
    }

    private function motivoTipo(AccesoSalidaOcasional $ocasional): string
    {
        if ($ocasional->permiso_id || $ocasional->permiso) {
            return 'permiso';
        }
        if (mb_strtolower(trim((string) $ocasional->motivo_texto)) === 'diligencia empresarial') {
            return 'diligencia';
        }

        return 'ocasional';
    }

    private function esperadoEn(Carbon $salida, mixed $hora): Carbon
    {
        $digits = preg_replace('/\D+/', '', (string) $hora) ?? '';
        if (strlen($digits) <= 2) {
            $digits = str_pad($digits, 2, '0', STR_PAD_LEFT).'00';
        } elseif (strlen($digits) === 3) {
            $digits = '0'.$digits;
        }
        $digits = str_pad(substr($digits, 0, 4), 4, '0');
        $esperado = $salida->copy()->setTime((int) substr($digits, 0, 2), (int) substr($digits, 2, 2));
        if ($esperado->lt($salida)) {
            $esperado->addDay();
        }

        return $esperado;
    }

    private function mensaje(string $cumplimiento, string $motivo, string $autoriza, int $minutos, Carbon $esperado): string
    {
        $base = $motivo.($autoriza !== '' ? ' · autoriza '.$autoriza : '');

        return match ($cumplimiento) {
            'a_tiempo' => $base.' · regresó a tiempo',
            'tarde' => $base.' · '.LlegadaTardeService::minutosLabel($minutos).' después de lo esperado',
            'abierta' => $base.' · regreso esperado '.LlegadaTardeService::horaLabel($esperado),
            default => $base.' · debía regresar '.LlegadaTardeService::horaLabel($esperado),
        };
    }

    private function diaCorto(Carbon $fecha): string
    {
        $dias = [1 => 'lun', 2 => 'mar', 3 => 'mié', 4 => 'jue', 5 => 'vie', 6 => 'sáb', 7 => 'dom'];

        return ($dias[$fecha->dayOfWeekIso] ?? '').' '.$fecha->format('d/m');
    }
}
