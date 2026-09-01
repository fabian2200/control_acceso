<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmpleadoController;
use App\Http\Controllers\Admin\FestivoController;
use App\Http\Controllers\Admin\HorarioController;
use App\Http\Controllers\Admin\LlegadaTardeController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\NovedadController;
use App\Http\Controllers\Admin\SalidaOcasionalController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

Route::get('/', function () {
    return auth('admin_acceso')->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('admin.login');
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

        Route::get('/festivos', [FestivoController::class, 'index'])->name('festivos.index');
        Route::get('/festivos/crear', [FestivoController::class, 'crear'])->name('festivos.crear');
        Route::post('/festivos', [FestivoController::class, 'guardar'])->name('festivos.guardar');
        Route::get('/festivos/{festivo}/editar', [FestivoController::class, 'editar'])->name('festivos.editar');
        Route::put('/festivos/{festivo}', [FestivoController::class, 'actualizar'])->name('festivos.actualizar');
        Route::delete('/festivos/{festivo}', [FestivoController::class, 'eliminar'])->name('festivos.eliminar');

        Route::get('/empleados', [EmpleadoController::class, 'index'])->name('empleados.index');
        Route::put('/empleados/{empleado}/horario', [EmpleadoController::class, 'asignar'])->name('empleados.asignar');
        Route::post('/empleados/asignar-lote', [EmpleadoController::class, 'asignarLote'])->name('empleados.asignar-lote');

        Route::get('/novedades', [NovedadController::class, 'index'])->name('novedades.index');
        Route::post('/novedades/{novedad}/aprobar', [NovedadController::class, 'aprobar'])->name('novedades.aprobar');
        Route::post('/novedades/{novedad}/rechazar', [NovedadController::class, 'rechazar'])->name('novedades.rechazar');

        Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('/logs/{empleado}', [LogController::class, 'show'])->name('logs.show');

        Route::get('/llegadas-tarde', [LlegadaTardeController::class, 'index'])->name('llegadas-tarde.index');
        Route::get('/llegadas-tarde/pdf', [LlegadaTardeController::class, 'pdf'])->name('llegadas-tarde.pdf');
        Route::get('/llegadas-tarde/excel', [LlegadaTardeController::class, 'excel'])->name('llegadas-tarde.excel');

        Route::get('/salidas-ocasionales', [SalidaOcasionalController::class, 'index'])->name('salidas-ocasionales.index');
        Route::get('/salidas-ocasionales/pdf', [SalidaOcasionalController::class, 'pdf'])->name('salidas-ocasionales.pdf');
        Route::get('/salidas-ocasionales/excel', [SalidaOcasionalController::class, 'excel'])->name('salidas-ocasionales.excel');
    });
});
