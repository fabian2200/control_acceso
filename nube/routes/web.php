<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmpleadoController;
use App\Http\Controllers\Admin\HorarioController;
use App\Http\Controllers\Admin\LlegadaTardeController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\NovedadController;
use Illuminate\Support\Facades\Route;

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
    });
});
