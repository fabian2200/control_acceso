<?php

namespace App\Http\Controllers\Kiosko;

use App\Http\Controllers\Controller;
use App\Services\AccesoService;
use App\Support\KioskoState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CedulaController extends Controller
{
    public function show(): View
    {
        KioskoState::clear();

        return view('kiosko.cedula');
    }

    public function identificar(Request $request, AccesoService $acceso): RedirectResponse
    {
        $data = $request->validate([
            'cedula' => ['required', 'string', 'min:5', 'max:15'],
        ], [
            'cedula.required' => 'Ingresa tu cédula.',
            'cedula.min' => 'La cédula es demasiado corta.',
        ]);

        $resultado = $acceso->identificar($data['cedula']);

        if (! $resultado['ok']) {
            return back()
                ->withInput()
                ->withErrors(['cedula' => 'Cédula no reconocida. Intenta de nuevo.']);
        }

        KioskoState::put([
            'empleado' => [
                'id' => $resultado['empleado']['id'],
                'user_id' => $resultado['empleado']['user_id'],
                'nombre' => $resultado['empleado']['nombre'],
                'primero' => $resultado['empleado']['primero'],
                'cargo' => $resultado['empleado']['cargo'],
                'identificacion' => $resultado['empleado']['identificacion'],
            ],
            'want_occasional' => $request->boolean('salida_ocasional'),
            'siguiente' => $resultado['siguiente'],
            'open_exit' => $resultado['openExit'],
            'sugerido' => $resultado['sugerido'],
            'entrada_despues_cierre' => false,
        ]);

        return redirect()->route('kiosko.reconocer');
    }

    public function cancelar(): RedirectResponse
    {
        KioskoState::clear();

        return redirect()->route('kiosko.cedula');
    }
}
