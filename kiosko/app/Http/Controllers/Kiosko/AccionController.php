<?php

namespace App\Http\Controllers\Kiosko;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Services\AccesoService;
use App\Support\KioskoState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccionController extends Controller
{
    public function show(AccesoService $acceso): View
    {
        $sesion = KioskoState::empleado();
        $empleado = Empleado::query()
            ->with(['asignacionHorario.horario.items'])
            ->find($sesion['id'] ?? 0);

        return view('kiosko.accion', [
            'empleado' => $sesion,
            'ahora' => now(),
            'botones' => $empleado
                ? $acceso->botonesJornada($empleado, now())
                : [],
        ]);
    }

    public function elegir(Request $request, AccesoService $acceso): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:entrada,salida,salida_ocasional'],
            'campo' => ['nullable', 'in:entrada_manana,salida_manana,entrada_tarde,salida_tarde'],
        ]);

        if ($data['tipo'] === 'salida_ocasional') {
            return redirect()->route('kiosko.motivo');
        }

        $sesion = KioskoState::empleado();
        $empleado = Empleado::query()
            ->with(['asignacionHorario.horario.items'])
            ->find($sesion['id'] ?? 0);

        if (! $empleado || ! $acceso->slotHabilitado($empleado, $data['tipo'], $data['campo'] ?? null, now())) {
            return back()->withErrors(['tipo' => 'Esa marca no está disponible en este momento.']);
        }

        KioskoState::put([
            'tipo' => $data['tipo'],
            'campo' => $data['campo'] ?? null,
        ]);

        return redirect()->route('kiosko.camara', ['tipo' => $data['tipo']]);
    }

    public function vencida(): View
    {
        return view('kiosko.vencida', [
            'overdue' => KioskoState::get('overdue'),
        ]);
    }

    public function reconocerVencida(): RedirectResponse
    {
        if (KioskoState::get('want_occasional')) {
            return redirect()->route('kiosko.motivo');
        }

        return redirect()->route('kiosko.accion');
    }

    public function regreso(): View
    {
        return view('kiosko.regreso', [
            'empleado' => KioskoState::empleadoVista(),
            'openExit' => KioskoState::get('open_exit'),
        ]);
    }

    public function confirmarRegreso(): RedirectResponse
    {
        KioskoState::put(['tipo' => 'regreso']);

        return redirect()->route('kiosko.camara', ['tipo' => 'regreso']);
    }
}
