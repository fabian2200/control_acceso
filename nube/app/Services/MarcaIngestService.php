<?php

namespace App\Services;

use App\Models\AccesoRegistro;
use App\Models\AccesoSalidaOcasional;
use App\Models\AccesoTerminal;
use Carbon\Carbon;
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
