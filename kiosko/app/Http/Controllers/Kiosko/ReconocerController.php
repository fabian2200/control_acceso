<?php

namespace App\Http\Controllers\Kiosko;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Services\AccesoService;
use App\Support\KioskoState;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReconocerController extends Controller
{
    public function show(): View
    {
        return view('kiosko.reconocer', [
            'empleado' => KioskoState::empleadoVista(),
            'continuarUrl' => route('kiosko.reconocer.continuar'),
        ]);
    }

    public function continuar(AccesoService $acceso): RedirectResponse
    {
        $siguiente = KioskoState::get('siguiente');

        if ($siguiente === 'return') {
            return redirect()->route('kiosko.regreso');
        }

        if (KioskoState::get('want_occasional')) {
            $sesion = KioskoState::empleado();
            $empleado = Empleado::query()
                ->with(['asignacionHorario.horario.items'])
                ->find($sesion['id'] ?? 0);

            if ($empleado && $acceso->puedeSalidaOcasional($empleado, now())) {
                return redirect()->route('kiosko.motivo');
            }
        }

        return redirect()->route('kiosko.accion');
    }
}
