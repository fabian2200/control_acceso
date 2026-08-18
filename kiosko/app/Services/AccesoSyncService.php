<?php

namespace App\Services;

use App\Models\AccesoEmpleadoHorario;
use App\Models\AccesoHorario;
use App\Models\AccesoHorarioItem;
use App\Models\AccesoRegistro;
use App\Models\AccesoSalidaOcasional;
use App\Models\AccesoSyncCheckpoint;
use App\Models\AccesoTerminal;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AccesoSyncService
{
    public function pendientes(): int
    {
        return AccesoRegistro::query()->where('sincronizado', false)->count()
            + AccesoSalidaOcasional::query()->where('sincronizado', false)->count();
    }

    public function estadoUi(): array
    {
        $pendientes = $this->pendientes();
        $ok = Cache::get('acceso.sync.ok');

        return [
            'pendientes' => $pendientes,
            'en_linea' => $ok === true,
            'etiqueta_red' => $ok === true ? 'En línea' : ($ok === false ? 'Sin NUBE' : 'Kiosko local'),
            'etiqueta_sync' => $pendientes > 0
                ? $pendientes.' pendiente'.($pendientes === 1 ? '' : 's')
                : 'Sincronizado',
        ];
    }

    public function ejecutar(): array
    {
        $base = config('acceso.api_url');
        $token = config('acceso.api_token');

        if ($base === '' || $token === '') {
            Cache::put('acceso.sync.ok', false, 3600);

            return ['ok' => false, 'error' => 'config'];
        }

        try {
            $health = $this->cliente($token)->get($base.'/api/health');

            if (! $health->successful() || ! $health->json('ok')) {
                Cache::put('acceso.sync.ok', false, 3600);

                return ['ok' => false, 'error' => 'health'];
            }

            $this->pullCatalogo($base, $token);
            $this->pushMarcas($base, $token);

            Cache::put('acceso.sync.ok', true, 3600);

            return ['ok' => true, 'pendientes' => $this->pendientes()];
        } catch (Throwable $e) {
            Cache::put('acceso.sync.ok', false, 3600);
            Log::warning('acceso.sync', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'red'];
        }
    }

    private function pullCatalogo(string $base, string $token): void
    {
        $checkpoint = AccesoSyncCheckpoint::query()->find('catalogo');
        $query = [];

        if ($checkpoint?->cursor) {
            $query['since'] = $checkpoint->cursor;
        }

        $response = $this->cliente($token)->timeout(120)->get($base.'/api/sync/catalogo', $query);
        $this->asegurarOk($response);

        $data = $response->json();

        DB::transaction(function () use ($data) {
            $this->upsertTabla('cargos', $data['cargos'] ?? []);
            $this->upsertTabla('empleados', $data['empleados'] ?? []);
            $this->upsertTabla('users', $data['users'] ?? []);
            $this->upsertTabla('permisos', $data['permisos'] ?? []);
            $this->upsertPorId(AccesoTerminal::class, $data['acceso_terminales'] ?? []);
            $this->upsertPorId(AccesoHorario::class, $data['acceso_horarios'] ?? []);
            $this->upsertPorId(AccesoHorarioItem::class, $data['acceso_horario_items'] ?? []);
            $this->reemplazarAsignaciones($data['acceso_empleado_horarios'] ?? []);
        });

        AccesoSyncCheckpoint::query()->updateOrCreate(
            ['tabla' => 'catalogo'],
            [
                'pulled_at' => now(),
                'cursor' => $data['server_time'] ?? now()->toIso8601String(),
                'updated_at' => now(),
            ]
        );
    }

    private function pushMarcas(string $base, string $token): void
    {
        $ocasionales = AccesoSalidaOcasional::query()
            ->where('sincronizado', false)
            ->orderBy('id')
            ->get();
        $registros = AccesoRegistro::query()
            ->where('sincronizado', false)
            ->orderBy('id')
            ->get();

        if ($ocasionales->isEmpty() && $registros->isEmpty()) {
            return;
        }

        $payload = [
            'ocasionales' => $ocasionales->map(fn (AccesoSalidaOcasional $row) => $this->payloadOcasional($row))->all(),
            'registros' => $registros->map(fn (AccesoRegistro $row) => $this->payloadRegistro($row))->all(),
        ];

        $response = $this->cliente($token)->post($base.'/api/sync/marcas', $payload);
        $this->asegurarOk($response);

        foreach ($response->json('ocasionales') ?? [] as $ack) {
            if (! ($ack['ok'] ?? false)) {
                continue;
            }

            $clave = $ack['clave'] ?? [];
            AccesoSalidaOcasional::query()
                ->where('empleado_id', $clave['empleado_id'] ?? 0)
                ->where('salida_en', $clave['salida_en'] ?? '')
                ->where('terminal_id', $clave['terminal_id'] ?? 0)
                ->where('sincronizado', false)
                ->update(['sincronizado' => true]);
        }

        foreach ($response->json('registros') ?? [] as $ack) {
            if (! ($ack['ok'] ?? false)) {
                continue;
            }

            $clave = $ack['clave'] ?? [];
            AccesoRegistro::query()
                ->where('empleado_id', $clave['empleado_id'] ?? 0)
                ->where('tipo', $clave['tipo'] ?? '')
                ->where('fecha', $clave['fecha'] ?? '')
                ->where('registrado_en', $clave['registrado_en'] ?? '')
                ->where('terminal_id', $clave['terminal_id'] ?? 0)
                ->where('sincronizado', false)
                ->update(['sincronizado' => true]);
        }
    }

    private function payloadOcasional(AccesoSalidaOcasional $row): array
    {
        return [
            'empleado_id' => $row->empleado_id,
            'id_horario' => $row->id_horario,
            'motivo_texto' => $row->motivo_texto,
            'permiso_id' => $row->permiso_id,
            'salida_en' => optional($row->salida_en)->format('Y-m-d H:i:s'),
            'hora_regreso_esperada' => $row->hora_regreso_esperada,
            'regreso_en' => optional($row->regreso_en)->format('Y-m-d H:i:s'),
            'minutos_tarde' => $row->minutos_tarde,
            'foto_salida' => $this->fotoPayload($row->foto_salida),
            'foto_salida_path' => $row->foto_salida,
            'foto_regreso' => $this->fotoPayload($row->foto_regreso),
            'foto_regreso_path' => $row->foto_regreso,
            'estado' => $row->estado,
            'revisada_rrhh' => $row->revisada_rrhh,
        ];
    }

    private function payloadRegistro(AccesoRegistro $row): array
    {
        $ocasional = $row->salidaOcasional;

        return [
            'empleado_id' => $row->empleado_id,
            'id_horario' => $row->id_horario,
            'tipo' => $row->tipo,
            'fecha' => optional($row->fecha)->toDateString(),
            'hora' => $row->hora,
            'registrado_en' => optional($row->registrado_en)->format('Y-m-d H:i:s'),
            'foto' => $this->fotoPayload($row->foto),
            'foto_path' => $row->foto,
            'hora_esperada' => $row->hora_esperada,
            'llego_tarde' => $row->llego_tarde,
            'llego_temprano' => $row->llego_temprano,
            'salio_temprano' => $row->salio_temprano,
            'salio_tarde' => $row->salio_tarde,
            'salida_ocasional' => $ocasional ? [
                'empleado_id' => $ocasional->empleado_id,
                'salida_en' => optional($ocasional->salida_en)->format('Y-m-d H:i:s'),
            ] : null,
        ];
    }

    private function fotoPayload(?string $path): ?array
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return [
            'contenido' => base64_encode(Storage::disk('public')->get($path)),
            'ext' => pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg',
        ];
    }

    private function upsertTabla(string $tabla, array $filas): void
    {
        $columnas = array_flip(Schema::getColumnListing($tabla));

        foreach ($filas as $fila) {
            $fila = array_intersect_key((array) $fila, $columnas);

            if (! isset($fila['id'])) {
                continue;
            }

            unset($fila['api_token']);

            foreach ($fila as $campo => $valor) {
                if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $valor)) {
                    $fila[$campo] = Carbon::parse($valor)->format('Y-m-d H:i:s');
                }
            }

            DB::table($tabla)->updateOrInsert(
                ['id' => $fila['id']],
                collect($fila)->except('id')->all()
            );
        }
    }

    private function upsertPorId(string $modelo, array $filas): void
    {
        $modelo::unguarded(function () use ($modelo, $filas) {
            foreach ($filas as $fila) {
                $fila = (array) $fila;

                if (! isset($fila['id'])) {
                    continue;
                }

                $modelo::query()->updateOrCreate(
                    ['id' => $fila['id']],
                    collect($fila)->except('id')->all()
                );
            }
        });
    }

    private function reemplazarAsignaciones(array $filas): void
    {
        $empleadoIds = [];

        AccesoEmpleadoHorario::unguarded(function () use ($filas, &$empleadoIds) {
            foreach ($filas as $fila) {
                $fila = (array) $fila;
                $empleadoId = (int) ($fila['empleado_id'] ?? 0);

                if ($empleadoId === 0 || empty($fila['horario_id'])) {
                    continue;
                }

                AccesoEmpleadoHorario::query()->updateOrCreate(
                    ['empleado_id' => $empleadoId],
                    [
                        'horario_id' => $fila['horario_id'],
                    ]
                );
                $empleadoIds[] = $empleadoId;
            }
        });

        $query = AccesoEmpleadoHorario::query();

        if ($empleadoIds !== []) {
            $query->whereNotIn('empleado_id', $empleadoIds);
        }

        $query->delete();
    }

    private function cliente(string $token)
    {
        return Http::timeout(20)
            ->acceptJson()
            ->withToken($token);
    }

    private function asegurarOk(Response $response): void
    {
        if ($response->failed()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }
    }
}
