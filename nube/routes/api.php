<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SyncCatalogoController;
use App\Http\Controllers\Api\SyncMarcasController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);

Route::middleware('terminal')->group(function () {
    Route::get('/sync/catalogo', [SyncCatalogoController::class, 'show']);
    Route::post('/sync/marcas', [SyncMarcasController::class, 'store']);
});
