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

        $overdue = $this->marcarVencidas($empleado);
        $openExit = $this->salidaAbiertaHoy($empleado);
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
            'overdue' => $overdue,
            'siguiente' => $openExit ? 'return' : ($overdue ? 'overdue' : 'action'),
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
            'regreso' => $this->registrarRegreso($empleado, $now, $terminal, $fotoPath),
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
            'terminal_id' => $terminal?->id,
            'motivo_texto' => $motivoTexto,
            'permiso_id' => $permiso?->id,
            'salida_en' => $now,
            'hora_regreso_esperada' => $horaRegreso,
            'foto_salida' => $fotoPath,
            'estado' => 'abierta',
        ]);

        $this->crearRegistro(
            $empleado,
            'salida',
            $now,
            $terminal,
            $fotoPath,
            $this->puntualidadVacia(),
            $ocasional->id
        );

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

    private function registrarRegreso(Empleado $empleado, Carbon $now, ?AccesoTerminal $terminal, ?string $fotoPath): array
    {
        $abierta = AccesoSalidaOcasional::query()
            ->where('empleado_id', $empleado->id)
            ->where('estado', 'abierta')
            ->latest('salida_en')
            ->first();

        $puntualidad = $this->calcularPuntualidadHora(
            $abierta?->hora_regreso_esperada ? substr((string) $abierta->hora_regreso_esperada, 0, 5) : null,
            $now,
            'entrada'
        );

        if ($abierta) {
            $abierta->update([
                'regreso_en' => $now,
                'foto_regreso' => $fotoPath,
                'minutos_tarde' => $puntualidad['llego_tarde'],
                'estado' => 'cerrada',
                'terminal_id' => $abierta->terminal_id ?: $terminal?->id,
            ]);
        }

        $this->crearRegistro(
            $empleado,
            'entrada',
            $now,
            $terminal,
            $fotoPath,
            $puntualidad,
            $abierta?->id
        );

        $salidaHora = $abierta?->salida_en?->format('H:i') ?? '';
        $motivo = $abierta?->motivo_texto ?? '';
        $esperado = $abierta ? substr((string) $abierta->hora_regreso_esperada, 0, 5) : '';

        return [
            'title' => 'Regreso confirmado',
            'time' => $now->format('H:i'),
            'color' => $puntualidad['llego_tarde'] > 0 ? '#d97706' : '#16a34a',
            'pill' => $puntualidad['llego_tarde'] > 0 || $puntualidad['llego_temprano'] > 0
                ? $this->pillPuntualidad('entrada', $puntualidad)
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
                $this->botonOcasional(),
            ];
        }

        $esManana = $this->esJornadaManana($item, $now);
        $botones = [];

        foreach ($this->definicionSlots() as $slot) {
            if (! $item->hora($slot['campo'])) {
                continue;
            }

            if (($slot['jornada'] === 'manana') !== $esManana) {
                continue;
            }

            $estado = $this->estadoSlot($empleado, $item, $slot, $now);
            $hora = $item->hora($slot['campo']);

            $botones[] = [
                'tipo' => $slot['tipo'],
                'campo' => $slot['campo'],
                'label' => $slot['label'],
                'sub' => $estado['enabled'] ? $hora : $estado['motivo'],
                'hora' => $hora,
                'clase' => $slot['clase'],
                'enabled' => $estado['enabled'],
            ];
        }

        $botones[] = $this->botonOcasional();

        return $botones;
    }

    public function slotHabilitado(Empleado $empleado, string $tipo, ?string $campo, Carbon $now): bool
    {
        if ($tipo === 'salida_ocasional' || $tipo === 'regreso') {
            return true;
        }

        foreach ($this->botonesJornada($empleado, $now) as $boton) {
            if ($boton['tipo'] === $tipo && ($boton['campo'] ?? null) === $campo) {
                return (bool) $boton['enabled'];
            }
        }

        return false;
    }

    private function botonOcasional(): array
    {
        return [
            'tipo' => 'salida_ocasional',
            'campo' => null,
            'label' => 'Salida ocasional',
            'sub' => 'Con regreso el mismo día',
            'clase' => 'action-occ',
            'enabled' => true,
        ];
    }

    private function definicionSlots(): array
    {
        return [
            ['campo' => 'entrada_manana', 'tipo' => 'entrada', 'jornada' => 'manana', 'label' => 'Entrada mañana', 'clase' => 'action-in', 'salida' => 'salida_manana', 'entrada' => 'entrada_manana'],
            ['campo' => 'salida_manana', 'tipo' => 'salida', 'jornada' => 'manana', 'label' => 'Salida mañana', 'clase' => 'action-out', 'salida' => 'salida_manana', 'entrada' => 'entrada_manana'],
            ['campo' => 'entrada_tarde', 'tipo' => 'entrada', 'jornada' => 'tarde', 'label' => 'Entrada tarde', 'clase' => 'action-in', 'salida' => 'salida_tarde', 'entrada' => 'entrada_tarde'],
            ['campo' => 'salida_tarde', 'tipo' => 'salida', 'jornada' => 'tarde', 'label' => 'Salida tarde', 'clase' => 'action-out', 'salida' => 'salida_tarde', 'entrada' => 'entrada_tarde'],
        ];
    }

    private function estadoSlot(Empleado $empleado, AccesoHorarioItem $item, array $slot, Carbon $now): array
    {
        if ($this->yaRegistrado($empleado, $slot['tipo'], $item->hora($slot['campo']), $now)) {
            return ['enabled' => false, 'motivo' => 'Ya registrada'];
        }

        return ['enabled' => true, 'motivo' => null];
    }

    private function esJornadaManana(AccesoHorarioItem $item, Carbon $now): bool
    {
        $corte = $this->carbonHora($now, $item->hora('entrada_tarde'));

        if (! $corte) {
            return true;
        }

        return $now->lt($corte);
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
        return AccesoRegistro::query()->create([
            'empleado_id' => $empleado->id,
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
            'sincronizado' => true,
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
            ->where('fecha', $now->toDateString())
            ->whereNull('salida_ocasional_id');
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
                ? $this->primerCampo($item, ['entrada_manana', 'entrada_tarde'])
                : $this->primerCampo($item, ['entrada_tarde', 'entrada_manana']);
        }

        return $conteo === 0
            ? $this->primerCampo($item, ['salida_manana', 'salida_tarde'])
            : $this->primerCampo($item, ['salida_tarde', 'salida_manana']);
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

        return Carbon::createFromFormat('Y-m-d H:i', $now->toDateString().' '.$hora, $now->timezone);
    }

    private function marcarVencidas(Empleado $empleado): ?array
    {
        $hoy = now()->toDateString();

        $vencidas = AccesoSalidaOcasional::query()
            ->where('empleado_id', $empleado->id)
            ->where('estado', 'abierta')
            ->whereDate('salida_en', '<', $hoy)
            ->get();

        if ($vencidas->isEmpty()) {
            return null;
        }

        $ultima = $vencidas->sortByDesc('salida_en')->first();

        AccesoSalidaOcasional::query()
            ->whereIn('id', $vencidas->pluck('id'))
            ->update(['estado' => 'vencida']);

        return [
            'date' => $ultima->salida_en->locale('es')->isoFormat('D MMM'),
            'time' => $ultima->salida_en->format('H:i'),
        ];
    }

    private function salidaAbiertaHoy(Empleado $empleado): ?array
    {
        $abierta = AccesoSalidaOcasional::query()
            ->where('empleado_id', $empleado->id)
            ->where('estado', 'abierta')
            ->whereDate('salida_en', now()->toDateString())
            ->latest('salida_en')
            ->first();

        if (! $abierta) {
            return null;
        }

        return [
            'time' => $abierta->salida_en->format('H:i'),
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
