<?php

namespace App\Services;

use App\Models\AccesoHorarioItem;
use App\Models\AccesoRegistro;
use App\Models\AccesoSalidaOcasional;
use App\Models\AccesoTerminal;
use App\Models\Empleado;
use App\Models\Permiso;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AccesoService
{
    private const HORAS_ANTES_ENTRADA = 2;

    public function identificar(string $cedula): array
    {
        $cedula = preg_replace('/\D+/', '', $cedula) ?? '';

        $empleado = Empleado::query()
            ->activos()
            ->with(['cargoRel', 'user'])
            ->where('identificacion', $cedula)
            ->first();

        if (! $empleado) {
            return ['ok' => false, 'error' => 'cedula'];
        }

        $openExit = $this->salidaAbierta($empleado);
        $sugerido = $this->sugerirTipo($empleado);

        return [
            'ok' => true,
            'empleado' => [
                'id' => $empleado->id,
                'user_id' => $empleado->user?->id,
                'nombre' => $empleado->nombre_completo,
                'primero' => Str::title(mb_strtolower($empleado->primer_nombre)),
                'cargo' => $empleado->cargo_nombre,
                'identificacion' => $empleado->identificacion,
                'foto' => $empleado->fotoSrc(),
            ],
            'sugerido' => $sugerido,
            'openExit' => $openExit,
            'overdue' => null,
            'siguiente' => $openExit ? 'return' : 'action',
        ];
    }

    public function registrar(array $data): array
    {
        $empleado = Empleado::query()
            ->activos()
            ->with(['asignacionHorario.horario.items', 'user'])
            ->findOrFail($data['empleado_id']);
        $tipo = $data['tipo'];
        $now = now();
        $terminal = AccesoTerminal::query()->where('codigo', config('acceso.terminal_codigo'))->first();
        $fotoPath = $this->guardarFoto($data['foto'] ?? null, $empleado->id, $tipo, $now);

        return match ($tipo) {
            'entrada' => $this->registrarEntrada($empleado, $now, $terminal, $fotoPath, $data['campo'] ?? null),
            'salida' => $this->registrarSalida($empleado, $now, $terminal, $fotoPath, $data['campo'] ?? null),
            'salida_ocasional' => $this->registrarOcasional($empleado, $now, $terminal, $fotoPath, $data),
            'regreso' => $this->cerrarOcasional($empleado, $now, $terminal, $fotoPath),
            default => throw new InvalidArgumentException('Tipo de registro no válido.'),
        };
    }

    private function registrarEntrada(Empleado $empleado, Carbon $now, ?AccesoTerminal $terminal, ?string $fotoPath, ?string $campo): array
    {
        $puntualidad = $this->calcularPuntualidad($empleado, 'entrada', $now, $campo);
        $this->crearRegistro($empleado, 'entrada', $now, $terminal, $fotoPath, $puntualidad);

        return [
            'title' => 'Entrada registrada',
            'time' => $now->format('H:i'),
            'color' => $puntualidad['llego_tarde'] > 0 ? '#d97706' : '#16a34a',
            'pill' => $this->pillPuntualidad('entrada', $puntualidad),
            'meta' => $puntualidad['hora_esperada'] ? 'Esperada '.substr($puntualidad['hora_esperada'], 0, 5) : null,
        ];
    }

    private function registrarSalida(Empleado $empleado, Carbon $now, ?AccesoTerminal $terminal, ?string $fotoPath, ?string $campo): array
    {
        $puntualidad = $this->calcularPuntualidad($empleado, 'salida', $now, $campo);
        $this->crearRegistro($empleado, 'salida', $now, $terminal, $fotoPath, $puntualidad);

        return [
            'title' => 'Salida registrada',
            'time' => $now->format('H:i'),
            'color' => $puntualidad['salio_tarde'] > 0 ? '#d97706' : '#2563eb',
            'pill' => $this->pillPuntualidad('salida', $puntualidad),
            'meta' => $puntualidad['hora_esperada']
                ? 'Esperada '.substr($puntualidad['hora_esperada'], 0, 5)
                : 'Fin de jornada',
        ];
    }

    private function registrarOcasional(Empleado $empleado, Carbon $now, ?AccesoTerminal $terminal, ?string $fotoPath, array $data): array
    {
        $userId = $data['user_id'] ?? $empleado->user?->id;
        $permiso = isset($data['permiso_id'], $userId)
            ? Permiso::query()->where('empleado', $userId)->find($data['permiso_id'])
            : null;
        $motivoTexto = $permiso?->motivoResumen()
            ?? ($data['motivo_texto'] ?? 'Otro');
        $horaRegreso = $this->normalizarHora(
            $permiso ? $permiso->horaFinDigitos() : ($data['hora_regreso'] ?? null)
        );

        $ocasional = AccesoSalidaOcasional::query()->create([
            'empleado_id' => $empleado->id,
            'id_horario' => $this->idHorarioEmpleado($empleado),
            'terminal_id' => $terminal?->id,
            'motivo_texto' => $motivoTexto,
            'permiso_id' => $permiso?->id,
            'salida_en' => $now,
            'hora_regreso_esperada' => $horaRegreso,
            'foto_salida' => $fotoPath,
            'estado' => 'abierta',
            'sincronizado' => false,
        ]);

        $caso = $this->clasificarOcasional($empleado, $now, $horaRegreso);

        if (in_array($caso, [3, 4, 5], true)) {
            $item = $this->itemHorarioHoy($empleado, $now);
            $campoSalida = $caso === 5
                ? $this->campoUltimaSalida($item)
                : 'salida_jornada_1';
            $puntualidad = $this->puntualidadSlot($empleado, 'salida', $now, $campoSalida);

            if (! $this->yaRegistrado($empleado, 'salida', $puntualidad['hora_esperada'] ? substr($puntualidad['hora_esperada'], 0, 5) : null, $now)) {
                $this->crearRegistro(
                    $empleado,
                    'salida',
                    $now,
                    $terminal,
                    $fotoPath,
                    $puntualidad,
                    $ocasional->id
                );
            }
        }

        $pillTexto = mb_strlen($motivoTexto) > 42
            ? rtrim(mb_substr($motivoTexto, 0, 40)).'…'
            : $motivoTexto;

        return [
            'title' => 'Salida ocasional registrada',
            'time' => $now->format('H:i'),
            'color' => '#d97706',
            'pill' => ['text' => $pillTexto, 'bg' => '#fffbeb', 'fg' => '#b45309'],
            'meta' => 'Regreso esperado '.$horaRegreso,
        ];
    }

    public function cerrarOcasionalAbierta(Empleado $empleado, ?string $fotoPath = null): array
    {
        $empleado->loadMissing(['asignacionHorario.horario.items', 'user']);
        $now = now();
        $terminal = AccesoTerminal::query()->where('codigo', config('acceso.terminal_codigo'))->first();

        return $this->cerrarOcasional($empleado, $now, $terminal, $fotoPath);
    }

    private function cerrarOcasional(Empleado $empleado, Carbon $now, ?AccesoTerminal $terminal, ?string $fotoPath): array
    {
        $abierta = AccesoSalidaOcasional::query()
            ->where('empleado_id', $empleado->id)
            ->where('estado', 'abierta')
            ->latest('salida_en')
            ->first();

        if (! $abierta) {
            return [
                'title' => 'Salida ocasional cerrada',
                'time' => $now->format('H:i'),
                'color' => '#16a34a',
                'pill' => ['text' => 'Salida cerrada', 'bg' => '#ecfdf3', 'fg' => '#15803d'],
                'meta' => null,
            ];
        }

        $esperado = substr((string) $abierta->hora_regreso_esperada, 0, 5);
        $caso = $this->clasificarOcasional($empleado, $abierta->salida_en, $esperado);

        if ($caso === 5) {
            $esperadoElDiaDeSalida = $this->carbonHora($abierta->salida_en, $esperado);
            $diaCierre = $esperadoElDiaDeSalida && $esperadoElDiaDeSalida->lt($abierta->salida_en)
                ? $abierta->salida_en->copy()->addDay()
                : $abierta->salida_en->copy();
            $horaCierre = $this->carbonHora($diaCierre, $esperado) ?? $diaCierre;
            $minutosTarde = 0;
        } else {
            $horaCierre = $now;
            $puntualidad = $this->calcularPuntualidadHora($esperado !== '' ? $esperado : null, $now, 'entrada');
            $minutosTarde = $puntualidad['llego_tarde'];
        }

        $abierta->update([
            'regreso_en' => $horaCierre,
            'foto_regreso' => $fotoPath,
            'minutos_tarde' => $minutosTarde,
            'estado' => 'cerrada',
            'terminal_id' => $abierta->terminal_id ?: $terminal?->id,
            'sincronizado' => false,
        ]);

        $salidaHora = $abierta->salida_en->format('H:i');
        $motivo = $abierta->motivo_texto ?? '';

        return [
            'title' => 'Salida ocasional cerrada',
            'time' => $horaCierre->format('H:i'),
            'color' => $minutosTarde > 0 ? '#d97706' : '#16a34a',
            'pill' => $minutosTarde > 0
                ? ['text' => 'Tarde · '.$minutosTarde.' min', 'bg' => '#fffbeb', 'fg' => '#b45309']
                : ['text' => 'Salida cerrada', 'bg' => '#ecfdf3', 'fg' => '#15803d'],
            'meta' => trim('Salió '.$salidaHora.($esperado !== '' ? ' · esperado '.$esperado : '').($motivo !== '' ? ' · '.$motivo : '')),
        ];
    }

    public function botonesJornada(Empleado $empleado, Carbon $now): array
    {
        $item = $this->itemHorarioHoy($empleado, $now);

        if (! $item || $item->esDescanso()) {
            return [
                [
                    'tipo' => 'entrada',
                    'campo' => null,
                    'label' => 'Entrada',
                    'sub' => 'Inicio de jornada',
                    'clase' => 'action-in',
                    'enabled' => true,
                ],
                [
                    'tipo' => 'salida',
                    'campo' => null,
                    'label' => 'Salida',
                    'sub' => 'Fin de jornada',
                    'clase' => 'action-out',
                    'enabled' => true,
                ],
                $this->botonOcasional($empleado, $now, null),
            ];
        }

        $botones = [];

        foreach ($this->definicionSlots() as $slot) {
            if (! $item->hora($slot['campo'])) {
                continue;
            }

            $estado = $this->estadoSlot($empleado, $item, $slot, $now);
            $hora = $item->hora($slot['campo']);
            $motivo = $estado['motivo'] ?? null;
            $mostrarHora = $estado['enabled'] || $motivo !== 'Ya registrada';

            $botones[] = [
                'tipo' => $slot['tipo'],
                'campo' => $slot['campo'],
                'label' => $slot['label'],
                'sub' => $mostrarHora ? $hora : $motivo,
                'nota' => (! $estado['enabled'] && $motivo && $motivo !== 'Ya registrada' && $motivo !== $hora)
                    ? $motivo
                    : null,
                'hora' => $hora,
                'clase' => $slot['clase'],
                'enabled' => $estado['enabled'],
            ];
        }

        $botones[] = $this->botonOcasional($empleado, $now, $item);

        return $botones;
    }

    public function slotHabilitado(Empleado $empleado, string $tipo, ?string $campo, Carbon $now): bool
    {
        if ($tipo === 'regreso') {
            return true;
        }

        foreach ($this->botonesJornada($empleado, $now) as $boton) {
            if ($boton['tipo'] === $tipo && ($boton['campo'] ?? null) === $campo) {
                return (bool) $boton['enabled'];
            }
        }

        return false;
    }

    public function puedeSalidaOcasional(Empleado $empleado, Carbon $now): bool
    {
        $item = $this->itemHorarioHoy($empleado, $now);

        return $this->tieneEntradaActual($empleado, $now, $item);
    }

    private function botonOcasional(Empleado $empleado, Carbon $now, ?AccesoHorarioItem $item): array
    {
        $puede = $this->tieneEntradaActual($empleado, $now, $item);

        return [
            'tipo' => 'salida_ocasional',
            'campo' => null,
            'label' => 'Salida ocasional',
            'sub' => $puede ? 'Se cierra al volver' : 'Marca primero tu entrada',
            'clase' => 'action-occ',
            'enabled' => $puede,
        ];
    }

    private function tieneEntradaActual(Empleado $empleado, Carbon $now, ?AccesoHorarioItem $item): bool
    {
        if (! $item || $item->esDescanso()) {
            $ultima = $this->queryRegistrosJornada($empleado, $now)
                ->whereIn('tipo', ['entrada', 'salida'])
                ->latest('registrado_en')
                ->first();

            return $ultima?->tipo === 'entrada';
        }

        $campo = $this->esJornada1($item, $now) ? 'entrada_jornada_1' : 'entrada_jornada_2';

        if (! $item->hora($campo)) {
            $campo = 'entrada_jornada_1';
        }

        return $this->yaRegistrado($empleado, 'entrada', $item->hora($campo), $now);
    }

    private function definicionSlots(): array
    {
        return [
            ['campo' => 'entrada_jornada_1', 'tipo' => 'entrada', 'jornada' => 1, 'label' => 'Entrada jornada 1', 'clase' => 'action-in', 'salida' => 'salida_jornada_1', 'entrada' => 'entrada_jornada_1'],
            ['campo' => 'salida_jornada_1', 'tipo' => 'salida', 'jornada' => 1, 'label' => 'Salida jornada 1', 'clase' => 'action-out', 'salida' => 'salida_jornada_1', 'entrada' => 'entrada_jornada_1'],
            ['campo' => 'entrada_jornada_2', 'tipo' => 'entrada', 'jornada' => 2, 'label' => 'Entrada jornada 2', 'clase' => 'action-in', 'salida' => 'salida_jornada_2', 'entrada' => 'entrada_jornada_2'],
            ['campo' => 'salida_jornada_2', 'tipo' => 'salida', 'jornada' => 2, 'label' => 'Salida jornada 2', 'clase' => 'action-out', 'salida' => 'salida_jornada_2', 'entrada' => 'entrada_jornada_2'],
        ];
    }

    private function estadoSlot(Empleado $empleado, AccesoHorarioItem $item, array $slot, Carbon $now): array
    {
        $hora = $item->hora($slot['campo']);

        if ($this->yaRegistrado($empleado, $slot['tipo'], $hora, $now)) {
            return ['enabled' => false, 'motivo' => 'Ya registrada'];
        }

        if ($slot['tipo'] === 'entrada') {
            $apertura = $this->horaAperturaEntrada($now, $hora);

            if ($apertura && $now->lt($apertura)) {
                return ['enabled' => false, 'motivo' => 'Puedes marcar desde las '.$apertura->format('H:i')];
            }
        }

        $esJornada1 = $this->esJornada1($item, $now);

        if ($slot['jornada'] === 1 && $slot['tipo'] === 'salida' && ! $esJornada1) {
            $inicioJ2 = $this->carbonHora($now, $item->hora('entrada_jornada_2'));

            if (! $inicioJ2 || $now->lt($inicioJ2)) {
                return ['enabled' => true, 'motivo' => null];
            }
        }

        if (($slot['jornada'] === 1) !== $esJornada1) {
            return ['enabled' => false, 'motivo' => $hora];
        }

        return ['enabled' => true, 'motivo' => null];
    }

    private function esJornada1(AccesoHorarioItem $item, Carbon $now): bool
    {
        $tieneJornada2 = $item->hora('entrada_jornada_2') || $item->hora('salida_jornada_2');

        if (! $tieneJornada2) {
            return true;
        }

        $limite = $this->limiteJornada1($item, $now);

        if ($limite && $now->lt($limite->copy()->addMinute())) {
            return true;
        }

        $aperturaJ2 = $this->horaAperturaEntrada($now, $item->hora('entrada_jornada_2'));

        if ($aperturaJ2 && $now->lt($aperturaJ2)) {
            return true;
        }

        return false;
    }

    private function horaAperturaEntrada(Carbon $now, ?string $hora): ?Carbon
    {
        $entrada = $this->carbonHora($now, $hora);

        return $entrada?->copy()->subHours(self::HORAS_ANTES_ENTRADA);
    }

    private function limiteJornada1(AccesoHorarioItem $item, Carbon $now): ?Carbon
    {
        $corte = $this->carbonHora($now, $item->hora('salida_jornada_1'))
            ?? $this->carbonHora($now, $item->hora('entrada_jornada_2'));

        if (! $corte) {
            return null;
        }

        return $corte->copy()->addMinutes($item->gabela('salida_jornada_1') ?? 0);
    }

    private function yaRegistrado(Empleado $empleado, string $tipo, ?string $horaEsperada, Carbon $now): bool
    {
        if (! $horaEsperada) {
            return false;
        }

        return $this->queryRegistrosJornada($empleado, $now)
            ->where('tipo', $tipo)
            ->whereTime('hora_esperada', strlen($horaEsperada) === 5 ? $horaEsperada.':00' : $horaEsperada)
            ->exists();
    }

    private function puntualidadSlot(Empleado $empleado, string $tipo, Carbon $now, string $campo): array
    {
        $item = $this->itemHorarioHoy($empleado, $now);

        if (! $item || ! $item->hora($campo)) {
            return $this->puntualidadVacia();
        }

        return $this->calcularPuntualidadHora($item->hora($campo), $now, $tipo, 0);
    }

    private function campoUltimaSalida(?AccesoHorarioItem $item): string
    {
        if ($item?->hora('salida_jornada_2')) {
            return 'salida_jornada_2';
        }

        return 'salida_jornada_1';
    }

    private function clasificarOcasional(Empleado $empleado, Carbon $salidaEn, string $horaRegreso): int
    {
        $item = $this->itemHorarioHoy($empleado, $salidaEn);
        $esperado = $this->carbonHora($salidaEn, $horaRegreso);

        if ($esperado && $esperado->lt($salidaEn)) {
            return 5;
        }

        if (! $item || $item->esDescanso()) {
            return 1;
        }

        $salidaJ1 = $this->carbonHora($salidaEn, $item->hora('salida_jornada_1'));
        $entradaJ2 = $this->carbonHora($salidaEn, $item->hora('entrada_jornada_2'));
        $salidaJ2 = $this->carbonHora($salidaEn, $item->hora('salida_jornada_2'));
        $ultimaSalida = $salidaJ2 ?? $salidaJ1;

        if ($ultimaSalida && ! $salidaEn->lt($ultimaSalida)) {
            return 5;
        }

        if ($ultimaSalida && $esperado && ! $esperado->lt($ultimaSalida)) {
            return 5;
        }

        if ($entradaJ2 && ! $salidaEn->lt($entradaJ2)) {
            return 2;
        }

        if ($salidaJ1 && $esperado && $esperado->lt($salidaJ1)) {
            return 1;
        }

        if ($salidaJ1 && ! $salidaEn->lt($salidaJ1)) {
            return 4;
        }

        if ($entradaJ2 && $esperado && ! $esperado->lt($entradaJ2)) {
            return 3;
        }

        return 1;
    }

    private function calcularPuntualidad(Empleado $empleado, string $tipo, Carbon $now, ?string $campo = null): array
    {
        $item = $this->itemHorarioHoy($empleado, $now);
        $campo = $campo ?: $this->campoHorario($empleado, $item, $tipo, $now);

        if (! $item || ! $campo) {
            return $this->puntualidadVacia();
        }

        return $this->calcularPuntualidadHora(
            $item->hora($campo),
            $now,
            $tipo,
            $item->gabela($campo) ?? 0
        );
    }

    private function calcularPuntualidadHora(?string $horaEsperada, Carbon $now, string $tipo, int $gabela = 0): array
    {
        $resultado = $this->puntualidadVacia();
        $hora = $this->carbonHora($now, $horaEsperada);

        if (! $hora) {
            return $resultado;
        }

        $resultado['hora_esperada'] = $hora->format('H:i:s');
        $limite = $hora->copy()->addMinutes($gabela);

        if ($now->greaterThan($limite)) {
            $minutos = (int) round($hora->diffInMinutes($now));
            if ($tipo === 'entrada') {
                $resultado['llego_tarde'] = $minutos;
            } else {
                $resultado['salio_tarde'] = $minutos;
            }
        } elseif ($now->lt($hora)) {
            $minutos = (int) round($now->diffInMinutes($hora));
            if ($tipo === 'entrada') {
                $resultado['llego_temprano'] = $minutos;
            } else {
                $resultado['salio_temprano'] = $minutos;
            }
        }

        return $resultado;
    }

    private function puntualidadVacia(): array
    {
        return [
            'hora_esperada' => null,
            'llego_tarde' => 0,
            'llego_temprano' => 0,
            'salio_temprano' => 0,
            'salio_tarde' => 0,
        ];
    }

    private function crearRegistro(
        Empleado $empleado,
        string $tipo,
        Carbon $now,
        ?AccesoTerminal $terminal,
        ?string $fotoPath,
        array $puntualidad,
        ?int $salidaOcasionalId = null
    ): AccesoRegistro {
        $existente = AccesoRegistro::query()
            ->where('empleado_id', $empleado->id)
            ->where('tipo', $tipo)
            ->where('fecha', $now->toDateString());

        if ($puntualidad['hora_esperada']) {
            $existente->whereTime('hora_esperada', $puntualidad['hora_esperada']);
        } else {
            $existente->whereTime('hora', $now->format('H:i:s'));
        }

        $ya = $existente->first();

        if ($ya) {
            if ($ya->id_horario === null) {
                $ya->update(['id_horario' => $this->idHorarioEmpleado($empleado)]);
            }

            return $ya;
        }

        return AccesoRegistro::query()->create([
            'empleado_id' => $empleado->id,
            'id_horario' => $this->idHorarioEmpleado($empleado),
            'terminal_id' => $terminal?->id,
            'salida_ocasional_id' => $salidaOcasionalId,
            'tipo' => $tipo,
            'fecha' => $now->toDateString(),
            'hora' => $now->format('H:i:s'),
            'registrado_en' => $now,
            'foto' => $fotoPath,
            'hora_esperada' => $puntualidad['hora_esperada'],
            'llego_tarde' => $puntualidad['llego_tarde'],
            'llego_temprano' => $puntualidad['llego_temprano'],
            'salio_temprano' => $puntualidad['salio_temprano'],
            'salio_tarde' => $puntualidad['salio_tarde'],
            'sincronizado' => false,
        ]);
    }

    private function pillPuntualidad(string $tipo, array $puntualidad): array
    {
        $tarde = $tipo === 'entrada' ? $puntualidad['llego_tarde'] : $puntualidad['salio_tarde'];
        $temprano = $tipo === 'entrada' ? $puntualidad['llego_temprano'] : $puntualidad['salio_temprano'];

        if ($tarde > 0) {
            return ['text' => 'Tarde · '.$tarde.' min', 'bg' => '#fffbeb', 'fg' => '#b45309'];
        }

        if ($temprano > 0) {
            return ['text' => 'Temprano · '.$temprano.' min', 'bg' => '#eff6ff', 'fg' => '#1d4ed8'];
        }

        return ['text' => 'A tiempo', 'bg' => '#ecfdf3', 'fg' => '#15803d'];
    }

    private function queryRegistrosJornada(Empleado $empleado, Carbon $now)
    {
        return AccesoRegistro::query()
            ->where('empleado_id', $empleado->id)
            ->where('fecha', $now->toDateString());
    }

    private function idHorarioEmpleado(Empleado $empleado): ?int
    {
        $empleado->loadMissing('asignacionHorario');

        return $empleado->asignacionHorario?->horario_id;
    }

    private function itemHorarioHoy(Empleado $empleado, Carbon $now): ?AccesoHorarioItem
    {
        $empleado->loadMissing('asignacionHorario.horario.items');
        $horario = $empleado->asignacionHorario?->horario;

        if (! $horario || ! $horario->activo) {
            return null;
        }

        return $horario->itemDelDia($now->isoWeekday());
    }

    private function campoHorario(Empleado $empleado, ?AccesoHorarioItem $item, string $tipo, Carbon $now): ?string
    {
        if (! $item) {
            return null;
        }

        $conteo = $this->queryRegistrosJornada($empleado, $now)
            ->where('tipo', $tipo)
            ->count();

        if ($tipo === 'entrada') {
            return $conteo === 0
                ? $this->primerCampo($item, ['entrada_jornada_1', 'entrada_jornada_2'])
                : $this->primerCampo($item, ['entrada_jornada_2', 'entrada_jornada_1']);
        }

        return $conteo === 0
            ? $this->primerCampo($item, ['salida_jornada_1', 'salida_jornada_2'])
            : $this->primerCampo($item, ['salida_jornada_2', 'salida_jornada_1']);
    }

    private function primerCampo(AccesoHorarioItem $item, array $campos): ?string
    {
        foreach ($campos as $campo) {
            if ($item->hora($campo)) {
                return $campo;
            }
        }

        return null;
    }

    private function carbonHora(Carbon $now, ?string $hora): ?Carbon
    {
        if (! $hora) {
            return null;
        }

        $hora = strlen($hora) === 5 ? $hora : substr($hora, 0, 5);

        return Carbon::createFromFormat('Y-m-d H:i', $now->toDateString().' '.$hora, $now->timezone);
    }

    public function salidaAbierta(Empleado $empleado): ?array
    {
        $abierta = AccesoSalidaOcasional::query()
            ->where('empleado_id', $empleado->id)
            ->where('estado', 'abierta')
            ->latest('salida_en')
            ->first();

        if (! $abierta) {
            return null;
        }

        return [
            'time' => $abierta->salida_en->format('H:i'),
            'date' => $abierta->salida_en->locale('es')->isoFormat('D MMM'),
            'today' => $abierta->salida_en->isToday(),
            'reason' => $abierta->motivo_texto,
            'back' => substr((string) $abierta->hora_regreso_esperada, 0, 5),
        ];
    }

    private function sugerirTipo(Empleado $empleado): string
    {
        $ultima = $this->queryRegistrosJornada($empleado, now())
            ->whereIn('tipo', ['entrada', 'salida'])
            ->latest('registrado_en')
            ->first();

        if (! $ultima || $ultima->tipo === 'salida') {
            return 'entrada';
        }

        return 'salida';
    }

    private function guardarFoto(?string $dataUrl, int $empleadoId, string $tipo, Carbon $now): ?string
    {
        if (! is_string($dataUrl) || ! str_starts_with($dataUrl, 'data:image')) {
            return null;
        }

        if (! preg_match('#^data:image/(png|jpe?g|webp);base64,(.+)$#i', $dataUrl, $m)) {
            return null;
        }

        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $bin = base64_decode($m[2], true);

        if ($bin === false) {
            return null;
        }

        $path = sprintf('acceso/%s/%s_%s_%s.%s', $now->format('Y-m-d'), $empleadoId, $tipo, $now->format('His'), $ext);
        Storage::disk('public')->put($path, $bin);

        return $path;
    }

    private function normalizarHora(?string $hora): string
    {
        $digits = preg_replace('/\D+/', '', (string) $hora) ?? '';
        $digits = str_pad(substr($digits, 0, 4), 4, '0');
        $h = min(23, (int) substr($digits, 0, 2));
        $i = min(59, (int) substr($digits, 2, 2));

        return sprintf('%02d:%02d', $h, $i);
    }
}
