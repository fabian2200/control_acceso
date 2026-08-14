<?php

namespace App\Http\Controllers\Kiosko;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use App\Support\KioskoState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OcasionalController extends Controller
{
    public function motivo(): View
    {
        return view('kiosko.motivo');
    }

    public function guardarMotivo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'origen' => ['required', 'in:permiso,otro'],
        ]);

        KioskoState::forget('permiso_id', 'hora_regreso');

        if ($data['origen'] === 'permiso') {
            KioskoState::put([
                'origen_ocasional' => 'permiso',
                'motivo_texto' => '',
            ]);

            return redirect()->route('kiosko.permisos');
        }

        KioskoState::put([
            'origen_ocasional' => 'otro',
            'motivo_texto' => 'Otro',
            'hora_regreso' => '',
        ]);

        return redirect()->route('kiosko.hora');
    }

    public function permisos(): View|RedirectResponse
    {
        $userId = KioskoState::userId();

        if (! KioskoState::empleado() || KioskoState::get('origen_ocasional') !== 'permiso') {
            return redirect()->route('kiosko.motivo');
        }

        $permisos = $userId
            ? Permiso::query()->activosEnFecha($userId)->get()
            : collect();

        return view('kiosko.permisos', [
            'permisos' => $permisos,
        ]);
    }

    public function elegirPermiso(Request $request): RedirectResponse
    {
        $userId = KioskoState::userId();

        if (! KioskoState::empleado() || ! $userId || KioskoState::get('origen_ocasional') !== 'permiso') {
            return redirect()->route('kiosko.motivo');
        }

        $data = $request->validate([
            'permiso_id' => ['required', 'integer', 'exists:permisos,id'],
        ]);

        $permiso = Permiso::query()
            ->activosEnFecha($userId)
            ->whereKey($data['permiso_id'])
            ->firstOrFail();

        KioskoState::put([
            'tipo' => 'salida_ocasional',
            'permiso_id' => $permiso->id,
            'motivo_texto' => $permiso->motivoResumen(),
            'hora_regreso' => $permiso->horaFinDigitos(),
        ]);

        return redirect()->route('kiosko.camara', ['tipo' => 'salida_ocasional']);
    }

    public function hora(): View|RedirectResponse
    {
        if (KioskoState::get('origen_ocasional') !== 'otro') {
            return redirect()->route('kiosko.motivo');
        }

        return view('kiosko.hora', [
            'motivo' => KioskoState::get('motivo_texto', 'Otro'),
            'hora' => KioskoState::get('hora_regreso', ''),
        ]);
    }

    public function guardarHora(Request $request): RedirectResponse
    {
        if (KioskoState::get('origen_ocasional') !== 'otro') {
            return redirect()->route('kiosko.motivo');
        }

        $data = $request->validate([
            'hora_regreso' => ['required', 'digits:4'],
        ], [
            'hora_regreso.required' => 'Indica la hora de regreso.',
            'hora_regreso.digits' => 'La hora debe tener 4 dígitos.',
        ]);

        KioskoState::put([
            'tipo' => 'salida_ocasional',
            'hora_regreso' => $data['hora_regreso'],
        ]);

        return redirect()->route('kiosko.camara', ['tipo' => 'salida_ocasional']);
    }
}
