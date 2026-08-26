<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarcaIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncSemillaController extends Controller
{
    public function show(Request $request, MarcaIngestService $ingest): JsonResponse
    {
        $semilla = $ingest->semillaMes($request);

        return response()->json([
            'ok' => true,
            'mes' => $semilla['mes'],
            'registros' => $semilla['registros'],
            'ocasionales' => $semilla['ocasionales'],
            'novedades' => $semilla['novedades'],
        ]);
    }
}
