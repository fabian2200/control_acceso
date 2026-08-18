<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccesoEmpleadoHorario;
use App\Models\AccesoHorario;
use App\Models\Empleado;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function show(): View
    {
        $horarios = AccesoHorario::query()->withCount('asignaciones')->orderBy('nombre')->get();
        $empleadosActivos = Empleado::query()->activos()->count();
        $asignados = AccesoEmpleadoHorario::query()->count();

        return view('admin.dashboard', [
            'horarios' => $horarios,
            'totalHorarios' => $horarios->count(),
            'empleadosActivos' => $empleadosActivos,
            'asignados' => $asignados,
            'sinHorario' => max(0, $empleadosActivos - $asignados),
        ]);
    }
}
