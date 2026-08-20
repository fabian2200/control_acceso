<?php

namespace App\Services;

use App\Models\AccesoNovedad;
use App\Models\AccesoTerminal;
use App\Models\Empleado;
use Carbon\Carbon;
use Throwable;

class NovedadIngestService
{
    public function ingest(array $payload, AccesoTerminal $terminal): array
    {
        $ack = [];

        foreach ($payload['novedades'] ?? [] as $row) {
            $ack[] = $this->upsert(is_array($row) ? $row : [], $terminal);
        }

        return [
            'ok' => true,
            'novedades' => $ack,
        ];
    }

    private function upsert(array $row, AccesoTerminal $terminal): array
    {
        $uuid = trim((string) ($row['uuid'] ?? ''));
        $clave = ['uuid' => $uuid !== '' ? $uuid : null];

        try {
            if ($uuid === '' || ! preg_match('/^[0-9a-fA-F-]{36}$/', $uuid)) {
                return ['uuid' => $uuid ?: null, 'ok' => false, 'error' => 'uuid'];
            }

            $empleadoId = (int) ($row['empleado_id'] ?? 0);
            if ($empleadoId < 1 || ! Empleado::query()->whereKey($empleadoId)->exists()) {
                return ['uuid' => $uuid, 'ok' => false, 'error' => 'empleado'];
            }

            $fecha = $this->fecha($row['fecha'] ?? null);
            $jornada = (int) ($row['jornada'] ?? 0);
            if (! $fecha || ! in_array($jornada, [1, 2], true)) {
                return ['uuid' => $uuid, 'ok' => false, 'error' => 'datos'];
            }

            $motivo = trim((string) ($row['motivo'] ?? ''));
            if ($motivo === '') {
                return ['uuid' => $uuid, 'ok' => false, 'error' => 'motivo'];
            }

            $quien = $row['quien_autoriza'] ?? null;
            if (is_string($quien)) {
                $quien = trim($quien);
                if ($quien === '') {
                    $quien = null;
                }
            } else {
                $quien = null;
            }

            if ($this->esDiligencia($motivo) && $quien === null) {
                return ['uuid' => $uuid, 'ok' => false, 'error' => 'autoriza'];
            }

            $existenteUuid = AccesoNovedad::query()->where('uuid', $uuid)->first();
            if ($existenteUuid) {
                $attrs = [
                    'empleado_id' => $empleadoId,
                    'terminal_id' => $terminal->id,
                    'fecha' => $fecha,
                    'jornada' => $jornada,
                    'hora_inicio_jornada' => $row['hora_inicio_jornada'] ?? null,
                    'hora_fin_jornada' => $row['hora_fin_jornada'] ?? null,
                    'motivo' => $motivo,
                    'quien_autoriza' => $quien,
                    'sincronizado' => true,
                ];
                if ($existenteUuid->aprobada === null) {
                    $existenteUuid->fill($attrs)->save();
                } else {
                    $existenteUuid->forceFill(['sincronizado' => true])->save();
                }

                return ['uuid' => $uuid, 'ok' => true];
            }

            $conflicto = AccesoNovedad::query()
                ->where('empleado_id', $empleadoId)
                ->whereDate('fecha', $fecha)
                ->where('jornada', $jornada)
                ->exists();

            if ($conflicto) {
                return ['uuid' => $uuid, 'ok' => false, 'error' => 'duplicado'];
            }

            AccesoNovedad::query()->create([
                'uuid' => $uuid,
                'empleado_id' => $empleadoId,
                'terminal_id' => $terminal->id,
                'fecha' => $fecha,
                'jornada' => $jornada,
                'hora_inicio_jornada' => $row['hora_inicio_jornada'] ?? null,
                'hora_fin_jornada' => $row['hora_fin_jornada'] ?? null,
                'motivo' => $motivo,
                'quien_autoriza' => $quien,
                'aprobada' => null,
                'sincronizado' => true,
            ]);

            return ['uuid' => $uuid, 'ok' => true];
        } catch (Throwable $e) {
            return ['uuid' => $clave['uuid'], 'ok' => false, 'error' => 'persist'];
        }
    }

    public function cambiosDesde(?string $since): array
    {
        $query = AccesoNovedad::query()->orderBy('updated_at')->orderBy('id');

        if (is_string($since) && trim($since) !== '') {
            try {
                $desde = Carbon::parse($since);
                $query->where('updated_at', '>=', $desde);
            } catch (Throwable) {
                // ignore since inválido
            }
        }

        return $query->get([
            'uuid',
            'empleado_id',
            'fecha',
            'jornada',
            'hora_inicio_jornada',
            'hora_fin_jornada',
            'motivo',
            'quien_autoriza',
            'aprobada',
            'updated_at',
        ])->map(function (AccesoNovedad $n) {
            return [
                'uuid' => $n->uuid,
                'empleado_id' => $n->empleado_id,
                'fecha' => optional($n->fecha)?->toDateString(),
                'jornada' => $n->jornada,
                'hora_inicio_jornada' => $n->hora_inicio_jornada,
                'hora_fin_jornada' => $n->hora_fin_jornada,
                'motivo' => $n->motivo,
                'quien_autoriza' => $n->quien_autoriza,
                'aprobada' => $n->aprobada,
                'updated_at' => optional($n->updated_at)?->toIso8601String(),
            ];
        })->values()->all();
    }

    private function fecha(mixed $valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function esDiligencia(string $motivo): bool
    {
        return mb_strtolower(trim($motivo)) === 'diligencia empresarial';
    }
}
