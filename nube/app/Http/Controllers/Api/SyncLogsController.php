<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccesoRegistro;
use App\Models\AccesoSalidaOcasional;
use App\Models\Empleado;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncLogsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $anio = (int) $request->query('anio', now()->year);
        $mes = (int) $request->query('mes', now()->month);
        $empleadoId = (int) $request->query('empleado_id', 0);

        if ($anio < 2000 || $mes < 1 || $mes > 12 || $empleadoId < 1) {
            return response()->json(['ok' => false, 'error' => 'datos'], 422);
        }

        $inicio = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/Bogota')->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        $empleado = Empleado::query()->find($empleadoId);

        if (! $empleado) {
            return response()->json(['ok' => false, 'error' => 'empleado'], 404);
        }

        $registros = AccesoRegistro::query()
            ->where('empleado_id', $empleadoId)
            ->whereDate('fecha', '>=', $inicio->toDateString())
            ->whereDate('fecha', '<=', $fin->toDateString())
            ->orderBy('registrado_en')
            ->get(['tipo', 'fecha', 'hora', 'registrado_en', 'hora_esperada', 'llego_tarde', 'llego_temprano', 'salio_temprano', 'salio_tarde']);

        $ocasionales = AccesoSalidaOcasional::query()
            ->where('empleado_id', $empleadoId)
            ->whereBetween('salida_en', [$inicio, $fin])
            ->orderBy('salida_en')
            ->get(['motivo_texto', 'autorizado_por', 'salida_en', 'hora_regreso_esperada', 'regreso_en', 'minutos_tarde', 'estado']);

        return response()->json([
            'ok' => true,
            'anio' => $anio,
            'mes' => $mes,
            'empleado' => [
                'id' => $empleado->id,
                'nombre' => trim(($empleado->nombres ?? '').' '.($empleado->apellidos ?? '')),
                'identificacion' => $empleado->identificacion,
            ],
            'registros' => $registros,
            'ocasionales' => $ocasionales,
        ]);
    }
}
