<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccesoEmpleadoHorario;
use App\Models\AccesoHorario;
use App\Models\AccesoHorarioItem;
use App\Models\AccesoTerminal;
use App\Models\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncCatalogoController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $since = $request->query('since');

        $horarios = AccesoHorario::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->get();

        $items = AccesoHorarioItem::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->get();

        $faltan = $items->pluck('horario_id')->unique()->diff($horarios->pluck('id'));

        if ($faltan->isNotEmpty()) {
            $horarios = $horarios
                ->concat(AccesoHorario::query()->whereIn('id', $faltan)->get())
                ->unique('id')
                ->values();
        }

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'cargos' => $this->snapshotTabla('cargos'),
            'empleados' => $this->snapshotTabla('empleados'),
            'users' => $this->snapshotTabla('users', ['remember_token']),
            'permisos' => Permiso::query()->orderBy('id')->get(),
            'acceso_terminales' => AccesoTerminal::query()
                ->orderBy('id')
                ->get(['id', 'codigo', 'nombre', 'ubicacion', 'activo', 'created_at', 'updated_at']),
            'acceso_horarios' => $horarios,
            'acceso_horario_items' => $items,
            'acceso_empleado_horarios' => AccesoEmpleadoHorario::query()->orderBy('id')->get(),
        ]);
    }

    private function snapshotTabla(string $tabla, array $excepto = []): array
    {
        return DB::table($tabla)
            ->orderBy('id')
            ->get()
            ->map(function ($fila) use ($excepto) {
                $datos = (array) $fila;
                foreach ($excepto as $campo) {
                    unset($datos[$campo]);
                }

                return $datos;
            })
            ->values()
            ->all();
    }
}
