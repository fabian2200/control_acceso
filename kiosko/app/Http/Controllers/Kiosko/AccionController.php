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
    public function show(AccesoService $acceso): View|RedirectResponse
    {
        $sesion = KioskoState::empleado();
        $empleado = Empleado::query()
            ->with(['asignacionHorario.horario.items'])
            ->find($sesion['id'] ?? 0);

        if ($empleado && $acceso->salidaAbierta($empleado) && ! KioskoState::get('entrada_despues_cierre')) {
            return redirect()->route('kiosko.regreso');
        }

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
            'campo' => ['nullable', 'in:entrada_jornada_1,salida_jornada_1,entrada_jornada_2,salida_jornada_2'],
        ]);

        $sesion = KioskoState::empleado();
        $empleado = Empleado::query()
            ->with(['asignacionHorario.horario.items'])
            ->find($sesion['id'] ?? 0);

        if ($empleado && $acceso->salidaAbierta($empleado) && $data['tipo'] !== 'salida_ocasional' && ! KioskoState::get('entrada_despues_cierre')) {
            return redirect()->route('kiosko.regreso');
        }

        if (! $empleado || ! $acceso->slotHabilitado($empleado, $data['tipo'], $data['campo'] ?? null, now())) {
            return back()->withErrors(['tipo' => 'Esa marca no está disponible en este momento.']);
        }

        if ($data['tipo'] === 'salida_ocasional') {
            return redirect()->route('kiosko.motivo');
        }

        KioskoState::put([
            'tipo' => $data['tipo'],
            'campo' => $data['campo'] ?? null,
        ]);

        return redirect()->route('kiosko.camara', ['tipo' => $data['tipo']]);
    }

    public function vencida(): RedirectResponse
    {
        return redirect()->route('kiosko.regreso');
    }

    public function reconocerVencida(): RedirectResponse
    {
        return redirect()->route('kiosko.regreso');
    }

    public function regreso(AccesoService $acceso): View|RedirectResponse
    {
        $openExit = KioskoState::get('open_exit');

        if (! $openExit) {
            $sesion = KioskoState::empleado();
            $empleado = Empleado::query()->find($sesion['id'] ?? 0);
            $openExit = $empleado ? $acceso->salidaAbierta($empleado) : null;
        }

        if (! $openExit) {
            return redirect()->route('kiosko.accion');
        }

        return view('kiosko.regreso', [
            'empleado' => KioskoState::empleadoVista(),
            'openExit' => $openExit,
        ]);
    }

    public function confirmarRegreso(AccesoService $acceso): RedirectResponse
    {
        $sesion = KioskoState::empleado();
        $empleado = Empleado::query()
            ->with(['asignacionHorario.horario.items', 'user'])
            ->find($sesion['id'] ?? 0);

        if (! $empleado) {
            return redirect()->route('kiosko.cedula');
        }

        $cierre = $acceso->cerrarOcasionalAbierta($empleado);

        KioskoState::put([
            'cierre' => $cierre,
            'open_exit' => null,
            'siguiente' => 'ask_entrada',
            'entrada_despues_cierre' => true,
        ]);

        return redirect()->route('kiosko.entrada.preguntar');
    }

    public function preguntarEntrada(): View|RedirectResponse
    {
        if (! KioskoState::get('entrada_despues_cierre')) {
            return redirect()->route('kiosko.accion');
        }

        return view('kiosko.preguntar-entrada', [
            'empleado' => KioskoState::empleadoVista(),
            'cierre' => KioskoState::get('cierre'),
        ]);
    }

    public function decidirEntrada(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registrar_entrada' => ['required', 'in:si,no'],
        ]);

        if ($data['registrar_entrada'] === 'no') {
            KioskoState::clear();

            return redirect()->route('kiosko.cedula');
        }

        return redirect()->route('kiosko.accion');
    }
}
