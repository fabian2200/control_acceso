<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SyncCatalogoController;
use App\Http\Controllers\Api\SyncLogsController;
use App\Http\Controllers\Api\SyncMarcasController;
use App\Http\Controllers\Api\SyncNovedadesController;
use App\Http\Controllers\Api\SyncSemillaController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);

Route::middleware('terminal')->group(function () {
    Route::get('/sync/catalogo', [SyncCatalogoController::class, 'show']);
    Route::get('/sync/semilla', [SyncSemillaController::class, 'show']);
    Route::post('/sync/marcas', [SyncMarcasController::class, 'store']);
    Route::get('/sync/logs', [SyncLogsController::class, 'show']);
    Route::post('/sync/novedades', [SyncNovedadesController::class, 'store']);
    Route::get('/sync/novedades', [SyncNovedadesController::class, 'show']);
});
