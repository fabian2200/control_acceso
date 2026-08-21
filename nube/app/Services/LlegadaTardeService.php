<?php

namespace App\Services;

use App\Models\AccesoHorarioItem;
use App\Models\AccesoNovedad;
use App\Models\AccesoRegistro;
use App\Models\Empleado;
use App\Models\Permiso;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LlegadaTardeService
{
    public const MESES = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    /**
     * @return array{
     *   anio:int,
     *   mes:int,
     *   empleado_id:?int,
     *   respaldo:string,
     *   kpis:array{total:int,justificadas:int,sin:int,incompletas:int,minutos:int,empleados:int},
     *   filas:list<array<string,mixed>>,
     *   empleados:Collection,
     *   anios:list<int>,
     *   meses:array<int,string>
     * }
     */
    public function informe(int $anio, int $mes, ?int $empleadoId, string $respaldo): array
    {
        $anio = max(2000, $anio);
        $mes = min(12, max(1, $mes));
        $respaldo = in_array($respaldo, ['todos', 'sin', 'novedad', 'permiso', 'incompleta'], true) ? $respaldo : 'todos';

        $inicio = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/Bogota')->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $ahora = now('America/Bogota');

        $empleados = Empleado::query()
            ->activos()
            ->with(['cargoRel', 'user', 'asignacionHorario.horario.items'])
            ->when($empleadoId, fn ($qb) => $qb->where('id', $empleadoId))
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->get();

        $empleadoIds = $empleados->pluck('id');

        $entradas = AccesoRegistro::query()
            ->with(['empleado.cargoRel', 'empleado.user', 'horario.items'])
            ->where('tipo', 'entrada')
            ->whereDate('fecha', '>=', $inicio->toDateString())
            ->whereDate('fecha', '<=', $fin->toDateString())
            ->when($empleadoId, fn ($qb) => $qb->where('empleado_id', $empleadoId))
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $novedades = $this->novedadesPorClave($empleadoIds, $inicio, $fin);
        $permisos = $this->permisosPorUsuario(
            $empleados->map(fn (Empleado $e) => $e->user?->id)->filter()->unique()->values(),
            $inicio,
            $fin
        );

        $entradasPorDia = $entradas->groupBy(function (AccesoRegistro $registro) {
            return $registro->empleado_id.'|'.$this->fechaCarbon($registro->fecha)->toDateString();
        });

        $filas = [];
        foreach ($entradas->where('llego_tarde', '>', 0) as $registro) {
            $filas[] = $this->armarFila($registro, $novedades, $permisos);
        }

        foreach ($this->filasIncompletas($empleados, $entradasPorDia, $novedades, $permisos, $inicio, $fin, $ahora) as $fila) {
            $filas[] = $fila;
        }

        usort($filas, function (array $a, array $b) {
            $fa = $a['fecha'] instanceof Carbon ? $a['fecha']->timestamp : 0;
            $fb = $b['fecha'] instanceof Carbon ? $b['fecha']->timestamp : 0;
            if ($fa !== $fb) {
                return $fa <=> $fb;
            }
            $n = strcmp((string) $a['nombre'], (string) $b['nombre']);
            if ($n !== 0) {
                return $n;
            }

            return ((int) $a['jornada']) <=> ((int) $b['jornada']);
        });

        $filas = array_values(array_filter($filas, function (array $fila) use ($respaldo) {
            return $respaldo === 'todos' || $fila['respaldo'] === $respaldo;
        }));

        $tardes = array_filter($filas, fn ($f) => ($f['tipo'] ?? 'tarde') !== 'incompleta');
        $incompletas = array_filter($filas, fn ($f) => ($f['tipo'] ?? '') === 'incompleta');
        $minutos = (int) array_sum(array_column($tardes, 'minutos'));
        $justificadas = count(array_filter($tardes, fn ($f) => in_array($f['respaldo'], ['novedad', 'permiso'], true)));
        $sin = count(array_filter($tardes, fn ($f) => $f['respaldo'] === 'sin'));
        $empleadosUnicos = count(array_unique(array_column($filas, 'empleado_id')));

        return [
            'anio' => $anio,
            'mes' => $mes,
            'empleado_id' => $empleadoId,
            'respaldo' => $respaldo,
            'kpis' => [
                'total' => count($tardes),
                'justificadas' => $justificadas,
                'sin' => $sin,
                'incompletas' => count($incompletas),
                'minutos' => $minutos,
                'empleados' => $empleadosUnicos,
            ],
            'filas' => $filas,
            'empleados' => $empleadoId
                ? Empleado::query()->activos()->orderBy('nombres')->orderBy('apellidos')->get()
                : $empleados,
            'anios' => $this->aniosDisponibles(),
            'meses' => self::MESES,
        ];
    }

    public function aniosDisponibles(): array
    {
        $min = AccesoRegistro::query()->min('fecha');
        $desde = $min ? (int) substr((string) $min, 0, 4) : (int) now('America/Bogota')->year;
        $hasta = (int) now('America/Bogota')->year;

        return range($desde, max($desde, $hasta));
    }

    public static function minutosLabel(int $minutos): string
    {
        if ($minutos < 60) {
            return $minutos.' min';
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $resto === 0 ? $horas.' h' : $horas.' h '.$resto.' min';
    }

    /**
     * @param  Collection<int, AccesoNovedad>  $novedades
     * @param  Collection<int, Collection<int, Permiso>>  $permisos
     * @return array<string, mixed>
     */
    private function armarFila(AccesoRegistro $registro, Collection $novedades, Collection $permisos): array
    {
        $empleado = $registro->empleado;
        $fecha = $this->fechaCarbon($registro->fecha);
        $item = $this->itemDelDia($registro, $fecha);
        $jornada = $this->inferirJornada($registro, $item);
        $clave = $registro->empleado_id.'|'.$fecha->toDateString().'|'.$jornada;
        $novedad = $novedades->get($clave);
        if (! $novedad) {
            $n1 = $novedades->get($registro->empleado_id.'|'.$fecha->toDateString().'|1');
            $n2 = $novedades->get($registro->empleado_id.'|'.$fecha->toDateString().'|2');
            $novedad = ($n1 && $n2) ? null : ($n1 ?? $n2);
        }
        $permiso = $novedad ? null : $this->permisoDeJornada(
            $permisos->get($empleado?->user?->id ?? 0) ?? collect(),
            $fecha,
            $jornada,
            $item,
            $registro->hora_esperada,
            $registro->hora
        );

        $respaldo = $novedad ? 'novedad' : ($permiso ? 'permiso' : 'sin');
        $motivo = $novedad?->motivo ?? ($permiso ? $permiso->motivoResumen(80) : null);
        $entrada = $this->hhmm($registro->hora_esperada);
        $marco = $this->hhmm($registro->hora);
        $minutos = (int) $registro->llego_tarde;

        return [
            'id' => $registro->id,
            'tipo' => 'tarde',
            'empleado_id' => $registro->empleado_id,
            'nombre' => $empleado?->nombre_completo ?: 'Empleado',
            'identificacion' => $empleado?->identificacion ?? '',
            'cargo' => $empleado?->cargo_nombre ?? 'Empleado',
            'fecha' => $fecha,
            'dia_label' => $this->diaCorto($fecha),
            'jornada' => $jornada,
            'entrada' => $entrada,
            'marco' => $marco,
            'minutos' => $minutos,
            'tarde_label' => self::minutosLabel($minutos),
            'respaldo' => $respaldo,
            'respaldo_label' => match ($respaldo) {
                'novedad' => 'Novedad',
                'permiso' => 'Permiso',
                default => 'Sin justificar',
            },
            'motivo' => $motivo,
            'titulo_detalle' => match ($respaldo) {
                'novedad' => 'NOVEDAD',
                'permiso' => 'PERMISO',
                default => 'SIN RESPALDO',
            },
            'mensaje' => match ($respaldo) {
                'novedad' => ($motivo ?: 'Novedad').' · jornada '.$jornada.($novedad?->quien_autoriza ? ' · autoriza '.$novedad->quien_autoriza : ''),
                'permiso' => ($motivo ?: 'Permiso aprobado').' · jornada '.$jornada,
                default => 'No hay permiso ni novedad para esta jornada',
            },
            'pie' => match ($respaldo) {
                'novedad' => 'Hay novedad pendiente o aprobada en el kiosko para esta jornada.',
                'permiso' => 'Hay un permiso aprobado de Workboard que cubre esta jornada.',
                default => 'No se encontró permiso aprobado ni novedad en el kiosko para este horario. Conviene verificar con el empleado.',
            },
        ];
    }

    /**
     * @param  Collection<int, Empleado>  $empleados
     * @param  Collection<string, Collection<int, AccesoRegistro>>  $entradasPorDia
     * @param  Collection<int, AccesoNovedad>  $novedades
     * @param  Collection<int, Collection<int, Permiso>>  $permisos
     * @return list<array<string, mixed>>
     */
    private function filasIncompletas(
        Collection $empleados,
        Collection $entradasPorDia,
        Collection $novedades,
        Collection $permisos,
        Carbon $inicio,
        Carbon $fin,
        Carbon $ahora
    ): array {
        $filas = [];
        $hasta = $fin->copy()->min($ahora->copy()->startOfDay());

        foreach ($empleados as $empleado) {
            $horario = $empleado->asignacionHorario?->horario;
            if (! $horario) {
                continue;
            }

            for ($dia = $inicio->copy(); $dia->lte($hasta); $dia->addDay()) {
                $item = $horario->items->firstWhere('dia_semana', $dia->dayOfWeekIso);
                if (! $item || $item->esDescanso()) {
                    continue;
                }

                $marcadas = [];
                $regs = $entradasPorDia->get($empleado->id.'|'.$dia->toDateString()) ?? collect();
                foreach ($regs as $registro) {
                    $marcadas[$this->inferirJornada($registro, $item)] = true;
                }

                foreach ([1, 2] as $jornada) {
                    $horaEntrada = $item->{'entrada_jornada_'.$jornada};
                    if (! $horaEntrada || isset($marcadas[$jornada])) {
                        continue;
                    }

                    $inicioJornada = $this->carbonHora($dia, $horaEntrada);
                    if ($inicioJornada === null || $inicioJornada->gte($ahora)) {
                        continue;
                    }

                    $permiso = $this->permisoDeJornada(
                        $permisos->get($empleado->user?->id ?? 0) ?? collect(),
                        $dia,
                        $jornada,
                        $item,
                        $horaEntrada,
                        $item->{'salida_jornada_'.$jornada}
                    );
                    if ($permiso) {
                        continue;
                    }

                    $filas[] = $this->armarFilaIncompleta($empleado, $dia->copy(), $jornada, $item, $novedades);
                }
            }
        }

        return $filas;
    }

    /**
     * @param  Collection<int, AccesoNovedad>  $novedades
     * @return array<string, mixed>
     */
    private function armarFilaIncompleta(
        Empleado $empleado,
        Carbon $fecha,
        int $jornada,
        AccesoHorarioItem $item,
        Collection $novedades
    ): array {
        $novedad = $novedades->get($empleado->id.'|'.$fecha->toDateString().'|'.$jornada);
        $entrada = $this->hhmm($item->{'entrada_jornada_'.$jornada});

        return [
            'id' => 'inc-'.$empleado->id.'-'.$fecha->toDateString().'-'.$jornada,
            'tipo' => 'incompleta',
            'empleado_id' => $empleado->id,
            'nombre' => $empleado->nombre_completo ?: 'Empleado',
            'identificacion' => $empleado->identificacion ?? '',
            'cargo' => $empleado->cargo_nombre ?? 'Empleado',
            'fecha' => $fecha,
            'dia_label' => $this->diaCorto($fecha),
            'jornada' => $jornada,
            'entrada' => $entrada,
            'marco' => '—',
            'minutos' => 0,
            'tarde_label' => 'Sin marca',
            'respaldo' => 'incompleta',
            'respaldo_label' => 'Incompleta',
            'motivo' => $novedad?->motivo,
            'titulo_detalle' => 'MARCACIÓN INCOMPLETA',
            'mensaje' => 'No hay entrada registrada para la jornada '.$jornada
                .($novedad ? ' · había novedad: '.$novedad->motivo : ''),
            'pie' => 'El empleado tenía horario este día y no alcanzó a marcar la entrada'
                .($novedad ? '. Existe novedad, pero no hay marcación de entrada.' : '.'),
        ];
    }

    private function novedadesPorClave(Collection $empleadoIds, Carbon $inicio, Carbon $fin): Collection
    {
        if ($empleadoIds->isEmpty()) {
            return collect();
        }

        return AccesoNovedad::query()
            ->whereIn('empleado_id', $empleadoIds)
            ->whereDate('fecha', '>=', $inicio->toDateString())
            ->whereDate('fecha', '<=', $fin->toDateString())
            ->where(fn ($qb) => $qb->whereNull('aprobada')->orWhere('aprobada', 1))
            ->get()
            ->keyBy(function (AccesoNovedad $novedad) {
                $fecha = $novedad->fecha instanceof Carbon
                    ? $novedad->fecha->toDateString()
                    : (string) $novedad->fecha;

                return $novedad->empleado_id.'|'.$fecha.'|'.$novedad->jornada;
            });
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, Collection<int, Permiso>>
     */
    private function permisosPorUsuario(Collection $userIds, Carbon $inicio, Carbon $fin): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        return Permiso::query()
            ->whereIn('empleado', $userIds)
            ->whereRaw('LOWER(estado) = ?', ['aprobado'])
            ->where(function ($qb) {
                $qb->whereNull('estado_reg')
                    ->orWhereRaw('UPPER(estado_reg) = ?', ['ACTIVO']);
            })
            ->whereNull('fecha_cancelacion')
            ->whereDate('fecha_inicio', '<=', $fin->toDateString())
            ->whereDate('fecha_fin', '>=', $inicio->toDateString())
            ->get()
            ->groupBy('empleado');
    }

    private function itemDelDia(AccesoRegistro $registro, Carbon $fecha): ?AccesoHorarioItem
    {
        $items = $registro->horario?->items;
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

        $novedadHora = $this->mins($registro->hora_esperada);
        if ($novedadHora !== null && $novedadHora >= 12 * 60 && $item?->entrada_jornada_2) {
            return 2;
        }

        return 1;
    }

    private function permisoDeJornada(
        Collection $permisos,
        Carbon $fecha,
        int $jornada,
        ?AccesoHorarioItem $item,
        mixed $horaEsperada = null,
        mixed $horaMarca = null
    ): ?Permiso {
        $dia = $fecha->toDateString();
        $prefijo = $jornada === 2 ? '2' : '1';
        $inicioJornada = $item?->{'entrada_jornada_'.$prefijo} ?? $horaEsperada;
        $finJornada = $item?->{'salida_jornada_'.$prefijo} ?? $horaMarca;

        foreach ($permisos as $permiso) {
            $ini = $permiso->fecha_inicio ? substr((string) $permiso->fecha_inicio, 0, 10) : null;
            $fin = $permiso->fecha_fin ? substr((string) $permiso->fecha_fin, 0, 10) : $ini;
            if ($ini && $dia < $ini) {
                continue;
            }
            if ($fin && $dia > $fin) {
                continue;
            }
            if ($this->horasSeSolapan($permiso->hora_inicio, $permiso->hora_fin, $inicioJornada, $finJornada)) {
                return $permiso;
            }
        }

        return null;
    }

    private function horasSeSolapan(mixed $permisoIni, mixed $permisoFin, mixed $jornadaIni, mixed $jornadaFin): bool
    {
        $pIni = $this->mins($permisoIni);
        $pFin = $this->mins($permisoFin);
        if ($pIni === null || $pFin === null) {
            return true;
        }

        $jIni = $this->mins($jornadaIni) ?? 0;
        $jFin = $this->mins($jornadaFin) ?? ($jIni + 240);
        if ($jFin <= $jIni) {
            $jFin += 24 * 60;
        }

        return $pIni < $jFin && $pFin > $jIni;
    }

    private function fechaCarbon(mixed $fecha): Carbon
    {
        if ($fecha instanceof Carbon) {
            return $fecha->copy()->timezone('America/Bogota')->startOfDay();
        }

        return Carbon::parse((string) $fecha, 'America/Bogota')->startOfDay();
    }

    private function carbonHora(Carbon $dia, mixed $hora): ?Carbon
    {
        $hhmm = $this->hhmm($hora);
        if ($hhmm === '—') {
            return null;
        }
        $partes = explode(':', $hhmm);

        return $dia->copy()->setTime((int) ($partes[0] ?? 0), (int) ($partes[1] ?? 0));
    }

    private function diaCorto(Carbon $fecha): string
    {
        $dias = [1 => 'lun', 2 => 'mar', 3 => 'mié', 4 => 'jue', 5 => 'vie', 6 => 'sáb', 7 => 'dom'];

        return ($dias[$fecha->dayOfWeekIso] ?? '').' '.$fecha->format('d/m');
    }

    private function hhmm(mixed $valor): string
    {
        if ($valor instanceof Carbon) {
            return $valor->format('H:i');
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '—';
        }

        return substr($texto, 0, 5);
    }

    private function mins(mixed $hora): ?int
    {
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
