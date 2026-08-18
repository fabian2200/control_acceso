<?php

namespace App\Http\Controllers\Kiosko;

use App\Http\Controllers\Controller;
use App\Services\AccesoSyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function run(AccesoSyncService $sync): JsonResponse
    {
        $resultado = $sync->ejecutar();

        return response()->json([
            'ok' => (bool) ($resultado['ok'] ?? false),
            'pendientes' => $sync->pendientes(),
            'ui' => $sync->estadoUi(),
        ]);
    }
}
