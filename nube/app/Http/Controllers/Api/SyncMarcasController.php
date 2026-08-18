<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccesoTerminal;
use App\Services\MarcaIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncMarcasController extends Controller
{
    public function store(Request $request, MarcaIngestService $ingest): JsonResponse
    {
        /** @var AccesoTerminal $terminal */
        $terminal = $request->attributes->get('terminal');

        $data = $request->validate([
            'ocasionales' => ['nullable', 'array'],
            'registros' => ['nullable', 'array'],
        ]);

        return response()->json($ingest->ingest($data, $terminal));
    }
}
