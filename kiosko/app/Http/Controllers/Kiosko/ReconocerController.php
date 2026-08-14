<?php

namespace App\Http\Controllers\Kiosko;

use App\Http\Controllers\Controller;
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

    public function continuar(): RedirectResponse
    {
        $siguiente = KioskoState::get('siguiente');

        if ($siguiente === 'return') {
            return redirect()->route('kiosko.regreso');
        }

        if ($siguiente === 'overdue') {
            return redirect()->route('kiosko.vencida');
        }

        if (KioskoState::get('want_occasional')) {
            return redirect()->route('kiosko.motivo');
        }

        return redirect()->route('kiosko.accion');
    }
}
