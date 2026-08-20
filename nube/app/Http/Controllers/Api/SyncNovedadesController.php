<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccesoTerminal;
use App\Services\NovedadIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncNovedadesController extends Controller
{
    public function store(Request $request, NovedadIngestService $ingest): JsonResponse
    {
        /** @var AccesoTerminal $terminal */
        $terminal = $request->attributes->get('terminal');

        $data = $request->validate([
            'novedades' => ['nullable', 'array'],
        ]);

        return response()->json($ingest->ingest($data, $terminal));
    }

    public function show(Request $request, NovedadIngestService $ingest): JsonResponse
    {
        $since = $request->query('since');

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'novedades' => $ingest->cambiosDesde(is_string($since) ? $since : null),
        ]);
    }
}
