<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccesoEmpleadoHorario;
use App\Models\AccesoHorario;
use App\Models\Empleado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmpleadoController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));
        $filtro = $request->input('filtro', 'todos');

        $empleados = Empleado::query()
            ->activos()
            ->with(['cargoRel', 'asignacionHorario.horario'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nombres', 'like', '%'.$q.'%')
                        ->orWhere('apellidos', 'like', '%'.$q.'%')
                        ->orWhere('identificacion', 'like', '%'.$q.'%');
                });
            })
            ->when($filtro === 'asignados', fn ($query) => $query->whereHas('asignacionHorario'))
            ->when($filtro === 'sin_horario', fn ($query) => $query->whereDoesntHave('asignacionHorario'))
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->get();

        return view('admin.empleados.index', [
            'empleados' => $empleados,
            'horarios' => AccesoHorario::query()->activos()->get(),
            'q' => $q,
            'filtro' => $filtro,
        ]);
    }

    public function asignar(Request $request, Empleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'horario_id' => ['nullable', 'integer', 'exists:acceso_horarios,id'],
        ]);

        if (empty($data['horario_id'])) {
            AccesoEmpleadoHorario::query()->where('empleado_id', $empleado->id)->delete();

            return back()->with('ok', 'Se quitó el horario de '.$empleado->nombre_completo.'.');
        }

        AccesoEmpleadoHorario::query()->updateOrCreate(
            ['empleado_id' => $empleado->id],
            ['horario_id' => $data['horario_id']]
        );

        return back()->with('ok', 'Horario asignado a '.$empleado->nombre_completo.'.');
    }

    public function asignarLote(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empleado_ids' => ['required', 'array', 'min:1'],
            'empleado_ids.*' => ['integer', 'exists:empleados,id'],
            'horario_id' => ['nullable', 'integer', 'exists:acceso_horarios,id'],
        ], [
            'empleado_ids.required' => 'Selecciona al menos un empleado.',
        ]);

        if (empty($data['horario_id'])) {
            AccesoEmpleadoHorario::query()
                ->whereIn('empleado_id', $data['empleado_ids'])
                ->delete();

            return back()->with('ok', 'Se quitó el horario de los empleados seleccionados.');
        }

        foreach ($data['empleado_ids'] as $empleadoId) {
            AccesoEmpleadoHorario::query()->updateOrCreate(
                ['empleado_id' => $empleadoId],
                ['horario_id' => $data['horario_id']]
            );
        }

        return back()->with('ok', 'Horario asignado a '.count($data['empleado_ids']).' empleados.');
    }
}
