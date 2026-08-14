<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmpleadoController;
use App\Http\Controllers\Admin\HorarioController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Kiosko\AccionController;
use App\Http\Controllers\Kiosko\CedulaController;
use App\Http\Controllers\Kiosko\OcasionalController;
use App\Http\Controllers\Kiosko\ReconocerController;
use App\Http\Controllers\Kiosko\RegistroController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CedulaController::class, 'show'])->name('kiosko.cedula');
Route::post('/identificar', [CedulaController::class, 'identificar'])->name('kiosko.identificar');
Route::get('/cancelar', [CedulaController::class, 'cancelar'])->name('kiosko.cancelar');

Route::middleware('kiosko.sesion')->group(function () {
    Route::get('/reconocer', [ReconocerController::class, 'show'])->name('kiosko.reconocer');
    Route::get('/reconocer/continuar', [ReconocerController::class, 'continuar'])->name('kiosko.reconocer.continuar');

    Route::get('/accion', [AccionController::class, 'show'])->name('kiosko.accion');
    Route::post('/accion', [AccionController::class, 'elegir'])->name('kiosko.accion.elegir');
    Route::get('/aviso', [AccionController::class, 'vencida'])->name('kiosko.vencida');
    Route::post('/aviso', [AccionController::class, 'reconocerVencida'])->name('kiosko.vencida.ack');
    Route::get('/regreso', [AccionController::class, 'regreso'])->name('kiosko.regreso');
    Route::post('/regreso', [AccionController::class, 'confirmarRegreso'])->name('kiosko.regreso.confirmar');

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

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin_acceso')->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'login'])->name('login.guardar');
    });

    Route::middleware('auth:admin_acceso')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'show'])->name('dashboard');

        Route::get('/horarios', [HorarioController::class, 'index'])->name('horarios.index');
        Route::get('/horarios/crear', [HorarioController::class, 'crear'])->name('horarios.crear');
        Route::post('/horarios', [HorarioController::class, 'guardar'])->name('horarios.guardar');
        Route::get('/horarios/{horario}/editar', [HorarioController::class, 'editar'])->name('horarios.editar');
        Route::put('/horarios/{horario}', [HorarioController::class, 'actualizar'])->name('horarios.actualizar');
        Route::delete('/horarios/{horario}', [HorarioController::class, 'eliminar'])->name('horarios.eliminar');

        Route::get('/empleados', [EmpleadoController::class, 'index'])->name('empleados.index');
        Route::put('/empleados/{empleado}/horario', [EmpleadoController::class, 'asignar'])->name('empleados.asignar');
        Route::post('/empleados/asignar-lote', [EmpleadoController::class, 'asignarLote'])->name('empleados.asignar-lote');
    });
});
