<?php

namespace App\Services;

use App\Models\AccesoNovedad;
use App\Models\AccesoRegistro;
use App\Models\AccesoSalidaOcasional;
use App\Models\AccesoTerminal;
use App\Support\FotoMarca;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MarcaIngestService
{
    public function ingest(array $payload, AccesoTerminal $terminal): array
    {
        $ocasionalesAck = [];
        $registrosAck = [];

        foreach ($payload['ocasionales'] ?? [] as $row) {
            $ocasionalesAck[] = $this->upsertOcasional(is_array($row) ? $row : [], $terminal);
        }

        foreach ($payload['registros'] ?? [] as $row) {
            $registrosAck[] = $this->upsertRegistro(is_array($row) ? $row : [], $terminal);
        }

        return [
            'ok' => true,
            'ocasionales' => $ocasionalesAck,
            'registros' => $registrosAck,
        ];
    }

    /**
     * @return array{mes:string, registros:list<array<string, mixed>>, ocasionales:list<array<string, mixed>>, novedades:list<array<string, mixed>>}
     */
    public function semillaMes(Request $request): array
    {
        $inicio = now('America/Bogota')->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $inicioDia = $inicio->toDateString();
        $finDia = $fin->toDateString();

        $ocasionales = AccesoSalidaOcasional::query()
            ->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('salida_en', [$inicio, $fin])
                    ->orWhere('estado', 'abierta');
            })
            ->orderBy('salida_en')
            ->orderBy('id')
            ->get();

        $registros = AccesoRegistro::query()
            ->whereDate('fecha', '>=', $inicioDia)
            ->whereDate('fecha', '<=', $finDia)
            ->orderBy('registrado_en')
            ->orderBy('id')
            ->get();

        $novedades = AccesoNovedad::query()
            ->whereDate('fecha', '>=', $inicioDia)
            ->whereDate('fecha', '<=', $finDia)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $ocasionalesPorId = $ocasionales->keyBy('id');
        $faltanIds = $registros
            ->pluck('salida_ocasional_id')
            ->filter()
            ->unique()
            ->diff($ocasionalesPorId->keys());

        if ($faltanIds->isNotEmpty()) {
            AccesoSalidaOcasional::query()
                ->whereIn('id', $faltanIds)
                ->get()
                ->each(function (AccesoSalidaOcasional $oc) use ($ocasionalesPorId) {
                    $ocasionalesPorId[$oc->id] = $oc;
                });
        }

        return [
            'mes' => $inicio->format('Y-m'),
            'registros' => $registros->map(
                fn (AccesoRegistro $row) => $this->serializarRegistro(
                    $row,
                    $request,
                    $row->salida_ocasional_id
                        ? ($ocasionalesPorId[$row->salida_ocasional_id] ?? null)
                        : null
                )
            )->values()->all(),
            'ocasionales' => $ocasionalesPorId->values()->map(
                fn (AccesoSalidaOcasional $row) => $this->serializarOcasional($row, $request)
            )->values()->all(),
            'novedades' => $novedades->map(
                fn (AccesoNovedad $n) => $this->serializarNovedad($n)
            )->values()->all(),
        ];
    }

    private function upsertOcasional(array $row, AccesoTerminal $terminal): array
    {
        $clave = $this->claveOcasional($row, $terminal);

        try {
            $salidaEn = $this->fechaHora($row['salida_en'] ?? null);

            if (! $salidaEn) {
                return ['clave' => $clave, 'ok' => false, 'error' => 'salida_en'];
            }

            $fotoSalida = $this->guardarFoto($row['foto_salida'] ?? null, (int) ($row['empleado_id'] ?? 0), 'salida_ocasional', $salidaEn);
            $regresoEn = $this->fechaHora($row['regreso_en'] ?? null);
            $fotoRegreso = $this->guardarFoto($row['foto_regreso'] ?? null, (int) ($row['empleado_id'] ?? 0), 'regreso', $regresoEn ?? $salidaEn);

            AccesoSalidaOcasional::query()->updateOrCreate(
                [
                    'empleado_id' => (int) $row['empleado_id'],
                    'salida_en' => $salidaEn,
                    'terminal_id' => $terminal->id,
                ],
                [
                    'id_horario' => $row['id_horario'] ?? null,
                    'motivo_texto' => $row['motivo_texto'] ?? null,
                    'autorizado_por' => $row['autorizado_por'] ?? $row['mandado_por'] ?? null,
                    'permiso_id' => $row['permiso_id'] ?? null,
                    'hora_regreso_esperada' => $row['hora_regreso_esperada'] ?? '00:00:00',
                    'regreso_en' => $regresoEn,
                    'minutos_tarde' => (int) ($row['minutos_tarde'] ?? 0),
                    'foto_salida' => $fotoSalida ?? ($row['foto_salida_path'] ?? null),
                    'foto_regreso' => $fotoRegreso ?? ($row['foto_regreso_path'] ?? null),
                    'estado' => $row['estado'] ?? 'abierta',
                    'revisada_rrhh' => (bool) ($row['revisada_rrhh'] ?? false),
                    'sincronizado' => true,
                ]
            );

            return ['clave' => $clave, 'ok' => true];
        } catch (Throwable $e) {
            return ['clave' => $clave, 'ok' => false, 'error' => 'persist'];
        }
    }

    private function upsertRegistro(array $row, AccesoTerminal $terminal): array
    {
        $clave = $this->claveRegistro($row, $terminal);

        try {
            $registradoEn = $this->fechaHora($row['registrado_en'] ?? null);

            if (! $registradoEn || empty($row['tipo']) || empty($row['empleado_id'])) {
                return ['clave' => $clave, 'ok' => false, 'error' => 'datos'];
            }

            $foto = $this->guardarFoto($row['foto'] ?? null, (int) $row['empleado_id'], (string) $row['tipo'], $registradoEn);
            $ocasionalId = $this->resolverOcasionalId($row, $terminal);

            AccesoRegistro::query()->updateOrCreate(
                [
                    'empleado_id' => (int) $row['empleado_id'],
                    'tipo' => $row['tipo'],
                    'fecha' => $row['fecha'] ?? $registradoEn->toDateString(),
                    'registrado_en' => $registradoEn,
                    'terminal_id' => $terminal->id,
                ],
                [
                    'id_horario' => $row['id_horario'] ?? null,
                    'salida_ocasional_id' => $ocasionalId,
                    'hora' => $row['hora'] ?? $registradoEn->format('H:i:s'),
                    'foto' => $foto ?? ($row['foto_path'] ?? null),
                    'hora_esperada' => $row['hora_esperada'] ?? null,
                    'llego_tarde' => (int) ($row['llego_tarde'] ?? 0),
                    'llego_temprano' => (int) ($row['llego_temprano'] ?? 0),
                    'salio_temprano' => (int) ($row['salio_temprano'] ?? 0),
                    'salio_tarde' => (int) ($row['salio_tarde'] ?? 0),
                    'sincronizado' => true,
                ]
            );

            return ['clave' => $clave, 'ok' => true];
        } catch (Throwable $e) {
            return ['clave' => $clave, 'ok' => false, 'error' => 'persist'];
        }
    }

    private function resolverOcasionalId(array $row, AccesoTerminal $terminal): ?int
    {
        $ref = $row['salida_ocasional'] ?? null;

        if (! is_array($ref) || empty($ref['empleado_id']) || empty($ref['salida_en'])) {
            return isset($row['salida_ocasional_id']) ? (int) $row['salida_ocasional_id'] : null;
        }

        $salidaEn = $this->fechaHora($ref['salida_en']);

        if (! $salidaEn) {
            return null;
        }

        return AccesoSalidaOcasional::query()
            ->where('empleado_id', (int) $ref['empleado_id'])
            ->where('salida_en', $salidaEn)
            ->where('terminal_id', $terminal->id)
            ->value('id');
    }

    private function claveOcasional(array $row, AccesoTerminal $terminal): array
    {
        return [
            'empleado_id' => (int) ($row['empleado_id'] ?? 0),
            'salida_en' => $row['salida_en'] ?? null,
            'terminal_id' => $terminal->id,
        ];
    }

    private function claveRegistro(array $row, AccesoTerminal $terminal): array
    {
        return [
            'empleado_id' => (int) ($row['empleado_id'] ?? 0),
            'tipo' => $row['tipo'] ?? null,
            'fecha' => $row['fecha'] ?? null,
            'registrado_en' => $row['registrado_en'] ?? null,
            'terminal_id' => $terminal->id,
        ];
    }

    private function fechaHora(mixed $valor): ?Carbon
    {
        if ($valor instanceof Carbon) {
            return $valor;
        }

        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return Carbon::parse($valor);
    }

    private function stamp(?Carbon $dt): ?string
    {
        if ($dt === null) {
            return null;
        }

        return $dt->copy()->timezone('America/Bogota')->format('Y-m-d H:i:s');
    }

    private function hora(mixed $valor): ?string
    {
        if ($valor instanceof Carbon) {
            return $valor->format('H:i:s');
        }
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $valor)) {
            return strlen($valor) === 5 ? $valor.':00' : $valor;
        }

        try {
            return Carbon::parse($valor)->format('H:i:s');
        } catch (Throwable) {
            return $valor;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarOcasional(AccesoSalidaOcasional $row, Request $request): array
    {
        return [
            'empleado_id' => $row->empleado_id,
            'id_horario' => $row->id_horario,
            'terminal_id' => $row->terminal_id,
            'motivo_texto' => $row->motivo_texto,
            'autorizado_por' => $row->autorizado_por,
            'permiso_id' => $row->permiso_id,
            'salida_en' => $this->stamp($row->salida_en),
            'hora_regreso_esperada' => $this->hora($row->hora_regreso_esperada),
            'regreso_en' => $this->stamp($row->regreso_en),
            'minutos_tarde' => (int) $row->minutos_tarde,
            'foto_salida' => FotoMarca::absoluta($request, $row->foto_salida),
            'foto_regreso' => FotoMarca::absoluta($request, $row->foto_regreso),
            'estado' => $row->estado,
            'revisada_rrhh' => (bool) $row->revisada_rrhh,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarRegistro(AccesoRegistro $row, Request $request, ?AccesoSalidaOcasional $ocasional): array
    {
        $fecha = $row->fecha;

        return [
            'empleado_id' => $row->empleado_id,
            'id_horario' => $row->id_horario,
            'terminal_id' => $row->terminal_id,
            'tipo' => $row->tipo,
            'fecha' => $fecha instanceof Carbon ? $fecha->toDateString() : (string) $fecha,
            'hora' => $this->hora($row->hora),
            'registrado_en' => $this->stamp($row->registrado_en),
            'foto' => FotoMarca::absoluta($request, $row->foto),
            'hora_esperada' => $this->hora($row->hora_esperada),
            'llego_tarde' => (int) $row->llego_tarde,
            'llego_temprano' => (int) $row->llego_temprano,
            'salio_temprano' => (int) $row->salio_temprano,
            'salio_tarde' => (int) $row->salio_tarde,
            'salida_ocasional' => $ocasional === null ? null : [
                'empleado_id' => $ocasional->empleado_id,
                'salida_en' => $this->stamp($ocasional->salida_en),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarNovedad(AccesoNovedad $n): array
    {
        return [
            'uuid' => $n->uuid,
            'empleado_id' => $n->empleado_id,
            'terminal_id' => $n->terminal_id,
            'fecha' => optional($n->fecha)?->toDateString(),
            'jornada' => $n->jornada,
            'hora_inicio_jornada' => $this->hora($n->hora_inicio_jornada),
            'hora_fin_jornada' => $this->hora($n->hora_fin_jornada),
            'motivo' => $n->motivo,
            'quien_autoriza' => $n->quien_autoriza,
            'aprobada' => $n->aprobada,
        ];
    }

    private function guardarFoto(mixed $foto, int $empleadoId, string $tipo, Carbon $cuando): ?string
    {
        if (! is_array($foto) || empty($foto['contenido'])) {
            return null;
        }

        $bin = base64_decode((string) $foto['contenido'], true);

        if ($bin === false) {
            throw new \RuntimeException('foto');
        }

        $ext = strtolower((string) ($foto['ext'] ?? 'jpg'));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $path = sprintf('acceso/%s/%s_%s_%s.%s', $cuando->format('Y-m-d'), $empleadoId, $tipo, $cuando->format('His'), $ext);
        Storage::disk('public')->put($path, $bin);

        return $path;
    }
}
