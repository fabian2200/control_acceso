<?php

namespace App\Services;

use App\Models\AccesoHorarioItem;
use App\Models\AccesoNovedad;
use App\Models\AccesoRegistro;
use App\Models\AccesoSalidaOcasional;
use App\Models\AccesoTerminal;
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
     *   kpis:array{total:int,temprano:int,justificadas:int,sin:int,incompletas:int,minutos:int,empleados:int},
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
        $respaldo = in_array($respaldo, ['todos', 'sin', 'novedad', 'permiso', 'diligencia', 'ocasional', 'incompleta', 'temprano'], true) ? $respaldo : 'todos';

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
            ->with(['empleado.cargoRel', 'empleado.user', 'empleado.asignacionHorario.horario.items', 'horario.items'])
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

        $salidasTemprano = AccesoRegistro::query()
            ->with([
                'empleado.cargoRel',
                'empleado.user',
                'empleado.asignacionHorario.horario.items',
                'horario.items',
                'salidaOcasional.permiso',
            ])
            ->where('tipo', 'salida')
            ->where(function ($qb) {
                $qb->where('salio_temprano', '>', 0)
                    ->orWhereHas('salidaOcasional', function ($q) {
                        $q->where(function ($inner) {
                            $inner->whereNotNull('permiso_id')
                                ->orWhereRaw('LOWER(TRIM(motivo_texto)) = ?', ['diligencia empresarial']);
                        });
                    });
            })
            ->whereDate('fecha', '>=', $inicio->toDateString())
            ->whereDate('fecha', '<=', $fin->toDateString())
            ->when($empleadoId, fn ($qb) => $qb->where('empleado_id', $empleadoId))
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $filas = [];
        foreach ($entradas->where('llego_tarde', '>', 0) as $registro) {
            $filas[] = $this->armarFila($registro, $novedades, $permisos);
        }
        foreach ($salidasTemprano as $registro) {
            $fila = $this->armarFila($registro, $novedades, $permisos);
            if (($fila['tipo'] ?? '') === 'temprano' && (int) $fila['minutos'] <= 0) {
                continue;
            }
            $filas[] = $fila;
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
            $j = ((int) $a['jornada']) <=> ((int) $b['jornada']);
            if ($j !== 0) {
                return $j;
            }
            $orden = ['tarde' => 0, 'incompleta' => 1, 'temprano' => 2];

            return ($orden[$a['tipo'] ?? 'tarde'] ?? 9) <=> ($orden[$b['tipo'] ?? 'tarde'] ?? 9);
        });

        $filas = array_values(array_filter($filas, function (array $fila) use ($respaldo) {
            if ($respaldo === 'todos') {
                return true;
            }
            if ($respaldo === 'temprano') {
                return ($fila['tipo'] ?? '') === 'temprano';
            }

            return $fila['respaldo'] === $respaldo;
        }));

        $tardes = array_filter($filas, fn ($f) => ($f['tipo'] ?? '') === 'tarde');
        $tempranos = array_filter($filas, fn ($f) => ($f['tipo'] ?? '') === 'temprano');
        $incompletas = array_filter($filas, fn ($f) => ($f['tipo'] ?? '') === 'incompleta');
        $incidencias = array_merge($tardes, $tempranos);
        $minutos = (int) array_sum(array_column($incidencias, 'minutos'));
        $justificadas = count(array_filter($incidencias, fn ($f) => in_array($f['respaldo'], ['novedad', 'permiso', 'diligencia', 'ocasional'], true)));
        $sin = count(array_filter($incidencias, fn ($f) => $f['respaldo'] === 'sin'));
        $empleadosUnicos = count(array_unique(array_column($filas, 'empleado_id')));

        return [
            'anio' => $anio,
            'mes' => $mes,
            'empleado_id' => $empleadoId,
            'respaldo' => $respaldo,
            'kpis' => [
                'total' => count($tardes),
                'temprano' => count($tempranos),
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
     * Hora en 12 h con AM/PM, p. ej. 09:52 AM o 02:30 PM.
     */
    public static function horaLabel(mixed $valor): string
    {
        if ($valor instanceof Carbon) {
            $local = $valor->copy()->timezone('America/Bogota');

            return self::de24a12($local->hour, $local->minute);
        }

        $texto = trim((string) $valor);
        if ($texto === '' || $texto === '—') {
            return '—';
        }

        $texto = trim((string) preg_replace('/\s*(a\.?\s*m\.?|p\.?\s*m\.?|am|pm)\s*$/i', '', $texto));

        if (str_contains($texto, 'T') || (str_contains($texto, ' ') && preg_match('/\d{4}-\d{2}/', $texto))) {
            try {
                return self::horaLabel(Carbon::parse($texto));
            } catch (\Throwable) {
                // seguir con dígitos de hora
            }
        }

        $digits = preg_replace('/\D+/', '', $texto) ?? '';
        if (strlen($digits) < 3 && strlen($digits) !== 2) {
            return '—';
        }
        if (strlen($digits) <= 2) {
            $digits = str_pad($digits, 2, '0', STR_PAD_LEFT).'00';
        } elseif (strlen($digits) === 3) {
            $digits = '0'.$digits;
        }
        $hora = (int) substr($digits, 0, 2);
        $minuto = (int) substr($digits, 2, 2);
        if ($hora > 23 || $minuto > 59) {
            return '—';
        }

        return self::de24a12($hora, $minuto);
    }

    public static function fechaHoraLabel(Carbon $fecha, string $formatoFecha = 'd/m/Y'): string
    {
        $local = $fecha->copy()->timezone('America/Bogota');

        return $local->format($formatoFecha).' '.self::horaLabel($local);
    }

    private static function de24a12(int $hora, int $minuto): string
    {
        $ampm = $hora < 12 ? 'AM' : 'PM';
        $h12 = $hora % 12;
        if ($h12 === 0) {
            $h12 = 12;
        }

        return sprintf('%02d:%02d %s', $h12, $minuto, $ampm);
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
        $esSalida = $registro->tipo === 'salida';
        $puntoPermiso = $esSalida
            ? ($registro->registrado_en ?? $registro->hora)
            : ($registro->hora_esperada ?? $item?->{'entrada_jornada_'.($jornada === 2 ? '2' : '1')});
        $permiso = $novedad ? null : $this->permisoDeJornada(
            $permisos->get($empleado?->user?->id ?? 0) ?? collect(),
            $fecha,
            $puntoPermiso,
        );

        $ocasional = $esSalida ? $registro->salidaOcasional : null;
        $ocasionalInfo = $this->respaldoOcasional($ocasional) ?? [];

        $respaldo = $ocasionalInfo['respaldo']
            ?? ($novedad ? 'novedad' : ($permiso ? 'permiso' : 'sin'));
        $motivo = $ocasionalInfo['motivo']
            ?? $novedad?->motivo
            ?? ($permiso ? $permiso->motivoResumen(80) : null);
        $esperada = self::horaLabel($registro->hora_esperada);
        $marco = $esSalida
            ? self::horaLabel($registro->registrado_en ?? $registro->hora)
            : self::horaLabel($registro->hora);
        $minutos = $esSalida
            ? $this->minutosSalidaTemprano($registro)
            : (int) $registro->llego_tarde;
        $tipo = $esSalida ? 'temprano' : 'tarde';
        $regreso = $ocasional ? self::horaLabel($ocasional->hora_regreso_esperada) : '—';
        $autoriza = trim((string) ($ocasional?->autorizado_por ?? $novedad?->quien_autoriza ?? ''));

        return [
            'id' => $registro->id,
            'tipo' => $tipo,
            'empleado_id' => $registro->empleado_id,
            'nombre' => $empleado?->nombre_completo ?: 'Empleado',
            'identificacion' => $empleado?->identificacion ?? '',
            'cargo' => $empleado?->cargo_nombre ?? 'Empleado',
            'horario' => $this->nombreHorario(
                $registro->horario?->nombre ?? $empleado?->asignacionHorario?->horario?->nombre
            ),
            'fecha' => $fecha,
            'dia_label' => $this->diaCorto($fecha),
            'jornada' => $jornada,
            'hora_label' => $esSalida ? 'Debía salir' : 'Debía entrar',
            'entrada' => $esperada,
            'marco' => $marco,
            'minutos' => $minutos,
            'tarde_label' => $esSalida ? self::minutosLabel($minutos).' antes' : self::minutosLabel($minutos),
            'respaldo' => $respaldo,
            'respaldo_label' => match ($respaldo) {
                'novedad' => 'Novedad',
                'permiso' => 'Permiso',
                'diligencia' => 'Diligencia',
                'ocasional' => 'Ocasional',
                default => 'Sin justificar',
            },
            'motivo' => $motivo,
            'titulo_detalle' => match (true) {
                $respaldo === 'diligencia' => 'DILIGENCIA EMPRESARIAL',
                $respaldo === 'ocasional' => 'SALIDA OCASIONAL',
                $esSalida && $respaldo === 'sin' => 'SALIDA TEMPRANO',
                $respaldo === 'novedad' => 'NOVEDAD',
                $respaldo === 'permiso' => 'PERMISO',
                default => 'SIN RESPALDO',
            },
            'mensaje' => match ($respaldo) {
                'novedad' => ($motivo ?: 'Novedad').' · jornada '.$jornada.($autoriza !== '' ? ' · autoriza '.$autoriza : ''),
                'permiso' => ($motivo ?: 'Permiso aprobado').' · jornada '.$jornada
                    .($ocasional ? ' · salida ocasional, regreso '.$regreso : ''),
                'diligencia' => 'Diligencia empresarial · jornada '.$jornada.' · regreso esperado '.$regreso
                    .($autoriza !== '' ? ' · autoriza '.$autoriza : ''),
                'ocasional' => ($motivo ?: 'Salida ocasional').' · jornada '.$jornada.' · regreso esperado '.$regreso,
                default => $esSalida
                    ? 'Salió antes de la hora de salida de la jornada '.$jornada
                    : 'No hay permiso ni novedad para esta jornada',
            },
            'pie' => match ($respaldo) {
                'novedad' => 'Hay novedad pendiente o aprobada en el kiosko para esta jornada.',
                'permiso' => $ocasional
                    ? 'La salida temprano está ligada a una salida ocasional con permiso de Workboard.'
                    : 'Hay un permiso aprobado de Workboard que cubre esta jornada.',
                'diligencia' => 'La salida temprano está ligada a una salida ocasional por diligencia empresarial.',
                'ocasional' => 'La salida temprano está ligada a una salida ocasional registrada en el kiosko.',
                default => $esSalida
                    ? 'No hay permiso, diligencia ni novedad que cubra esta salida anticipada.'
                    : 'No se encontró permiso aprobado ni novedad en el kiosko para este horario. Conviene verificar con el empleado.',
            },
        ];
    }

    /**
     * @return array{respaldo:string, motivo:?string}|null
     */
    private function respaldoOcasional(?AccesoSalidaOcasional $ocasional): ?array
    {
        if ($ocasional === null) {
            return null;
        }

        if ($ocasional->permiso_id || $ocasional->permiso) {
            return [
                'respaldo' => 'permiso',
                'motivo' => $ocasional->permiso?->motivoResumen(80) ?: ($ocasional->motivo_texto ?: 'Permiso'),
            ];
        }

        if ($this->esDiligenciaEmpresarial($ocasional->motivo_texto)) {
            return [
                'respaldo' => 'diligencia',
                'motivo' => 'Diligencia empresarial',
            ];
        }

        $motivo = trim((string) $ocasional->motivo_texto);

        return [
            'respaldo' => 'ocasional',
            'motivo' => $motivo !== '' ? $motivo : 'Salida ocasional',
        ];
    }

    private function esDiligenciaEmpresarial(mixed $motivo): bool
    {
        return mb_strtolower(trim((string) $motivo)) === 'diligencia empresarial';
    }

    private function minutosSalidaTemprano(AccesoRegistro $registro): int
    {
        $minutos = (int) $registro->salio_temprano;
        if ($minutos > 0) {
            return $minutos;
        }

        $esperada = $this->carbonHora($this->fechaCarbon($registro->fecha), $registro->hora_esperada);
        $marco = $registro->registrado_en
            ? Carbon::parse($registro->registrado_en)->timezone('America/Bogota')
            : null;
        if ($esperada === null || $marco === null || $marco->gte($esperada)) {
            return 0;
        }

        return (int) $marco->diffInMinutes($esperada);
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
        $desde = $inicio->copy();
        $inicioSistema = $this->fechaInicioFuncionamiento();
        if ($inicioSistema && $desde->lt($inicioSistema)) {
            $desde = $inicioSistema->copy();
        }
        if ($desde->gt($hasta)) {
            return [];
        }

        foreach ($empleados as $empleado) {
            $horario = $empleado->asignacionHorario?->horario;
            if (! $horario) {
                continue;
            }

            for ($dia = $desde->copy(); $dia->lte($hasta); $dia->addDay()) {
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
                        $horaEntrada,
                    );
                    if ($permiso) {
                        continue;
                    }

                    $filas[] = $this->armarFilaIncompleta($empleado, $dia->copy(), $jornada, $item, $novedades, $horario->nombre);
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
        Collection $novedades,
        ?string $horarioNombre = null
    ): array {
        $novedad = $novedades->get($empleado->id.'|'.$fecha->toDateString().'|'.$jornada);
        $entrada = self::horaLabel($item->{'entrada_jornada_'.$jornada});

        return [
            'id' => 'inc-'.$empleado->id.'-'.$fecha->toDateString().'-'.$jornada,
            'tipo' => 'incompleta',
            'empleado_id' => $empleado->id,
            'nombre' => $empleado->nombre_completo ?: 'Empleado',
            'identificacion' => $empleado->identificacion ?? '',
            'cargo' => $empleado->cargo_nombre ?? 'Empleado',
            'horario' => $this->nombreHorario($horarioNombre ?? $empleado->asignacionHorario?->horario?->nombre),
            'fecha' => $fecha,
            'dia_label' => $this->diaCorto($fecha),
            'jornada' => $jornada,
            'hora_label' => 'Debía entrar',
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

    private function nombreHorario(?string $nombre): string
    {
        $nombre = trim((string) $nombre);

        return $nombre !== '' ? $nombre : 'Sin horario';
    }

    private function fechaInicioFuncionamiento(): ?Carbon
    {
        $valor = AccesoTerminal::query()
            ->whereNotNull('fecha_inicio_funcionamiento')
            ->min('fecha_inicio_funcionamiento');

        if ($valor === null || $valor === '') {
            return null;
        }

        return $this->fechaCarbon($valor);
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
        $prefijo = $registro->tipo === 'salida' ? 'salida_jornada_' : 'entrada_jornada_';
        if ($item && $esperada !== null) {
            $e1 = $this->mins($item->{$prefijo.'1'});
            $e2 = $this->mins($item->{$prefijo.'2'});
            if ($e2 !== null && $e1 !== null) {
                return abs($esperada - $e2) < abs($esperada - $e1) ? 2 : 1;
            }
            if ($e2 !== null && $esperada === $e2) {
                return 2;
            }
        }

        $novedadHora = $this->mins($registro->hora_esperada);
        $slot2 = $item?->{$prefijo.'2'};
        if ($novedadHora !== null && $novedadHora >= 12 * 60 && $slot2) {
            return 2;
        }

        return 1;
    }

    private function permisoDeJornada(
        Collection $permisos,
        Carbon $fecha,
        mixed $punto,
    ): ?Permiso {
        $dia = $fecha->toDateString();

        foreach ($permisos as $permiso) {
            $ini = $permiso->fecha_inicio ? substr((string) $permiso->fecha_inicio, 0, 10) : null;
            $fin = $permiso->fecha_fin ? substr((string) $permiso->fecha_fin, 0, 10) : $ini;
            if ($ini && $dia < $ini) {
                continue;
            }
            if ($fin && $dia > $fin) {
                continue;
            }
            if ($this->permisoCubreHora($permiso->hora_inicio, $permiso->hora_fin, $punto)) {
                return $permiso;
            }
        }

        return null;
    }

    private function permisoCubreHora(mixed $permisoIni, mixed $permisoFin, mixed $punto): bool
    {
        $pIni = $this->mins($permisoIni);
        $pFin = $this->mins($permisoFin);
        if ($pIni === null || $pFin === null) {
            return true;
        }

        $mins = $this->mins($punto);
        if ($mins === null) {
            return false;
        }

        if ($pFin < $pIni) {
            $pFin += 24 * 60;
            if ($mins < $pIni) {
                $mins += 24 * 60;
            }
        }

        return $pIni <= $mins && $mins <= $pFin;
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
