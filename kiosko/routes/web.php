<?php

use App\Http\Controllers\Kiosko\AccionController;
use App\Http\Controllers\Kiosko\CedulaController;
use App\Http\Controllers\Kiosko\OcasionalController;
use App\Http\Controllers\Kiosko\ReconocerController;
use App\Http\Controllers\Kiosko\RegistroController;
use App\Http\Controllers\Kiosko\SyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CedulaController::class, 'show'])->name('kiosko.cedula');
Route::post('/identificar', [CedulaController::class, 'identificar'])->name('kiosko.identificar');
Route::get('/cancelar', [CedulaController::class, 'cancelar'])->name('kiosko.cancelar');
Route::post('/sincronizar', [SyncController::class, 'run'])->name('kiosko.sync');

Route::middleware('kiosko.sesion')->group(function () {
    Route::get('/reconocer', [ReconocerController::class, 'show'])->name('kiosko.reconocer');
    Route::get('/reconocer/continuar', [ReconocerController::class, 'continuar'])->name('kiosko.reconocer.continuar');

    Route::get('/accion', [AccionController::class, 'show'])->name('kiosko.accion');
    Route::post('/accion', [AccionController::class, 'elegir'])->name('kiosko.accion.elegir');
    Route::get('/aviso', [AccionController::class, 'vencida'])->name('kiosko.vencida');
    Route::post('/aviso', [AccionController::class, 'reconocerVencida'])->name('kiosko.vencida.ack');
    Route::get('/regreso', [AccionController::class, 'regreso'])->name('kiosko.regreso');
    Route::post('/regreso', [AccionController::class, 'confirmarRegreso'])->name('kiosko.regreso.confirmar');
    Route::get('/entrada-ocasional', [AccionController::class, 'preguntarEntrada'])->name('kiosko.entrada.preguntar');
    Route::post('/entrada-ocasional', [AccionController::class, 'decidirEntrada'])->name('kiosko.entrada.decidir');

    Route::get('/motivo', [OcasionalController::class, 'motivo'])->name('kiosko.motivo');
    Route::post('/motivo', [OcasionalController::class, 'guardarMotivo'])->name('kiosko.motivo.guardar');
    Route::get('/permisos', [OcasionalController::class, 'permisos'])->name('kiosko.permisos');
    Route::post('/permisos', [OcasionalController::class, 'elegirPermiso'])->name('kiosko.permisos.elegir');
    Route::get('/hora-regreso', [OcasionalController::class, 'hora'])->name('kiosko.hora');
    Route::post('/hora-regreso', [OcasionalController::class, 'guardarHora'])->name('kiosko.hora.guardar');

    Route::get('/camara/{tipo}', [RegistroController::class, 'camara'])->name('kiosko.camara');
    Route::post('/registrar', [RegistroController::class, 'guardar'])->name('kiosko.registrar');
    Route::get('/confirmacion', [RegistroController::class, 'confirmacion'])->name('kiosko.confirmacion');
});
