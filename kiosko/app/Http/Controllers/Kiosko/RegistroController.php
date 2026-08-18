<?php

namespace App\Http\Controllers\Kiosko;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Services\AccesoService;
use App\Support\KioskoState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistroController extends Controller
{
    public function camara(string $tipo, AccesoService $acceso): View|RedirectResponse
    {
        if (! in_array($tipo, ['entrada', 'salida', 'salida_ocasional', 'regreso'], true)) {
            return redirect()->route('kiosko.accion');
        }

        if ($tipo === 'regreso') {
            return redirect()->route('kiosko.regreso');
        }

        if ($tipo === 'salida_ocasional' && ! KioskoState::get('hora_regreso')) {
            return redirect()->route('kiosko.hora');
        }

        $sesion = KioskoState::empleado();
        $empleado = $sesion ? Empleado::query()->find($sesion['id'] ?? 0) : null;

        if ($empleado && $acceso->salidaAbierta($empleado) && $tipo !== 'salida_ocasional' && ! KioskoState::get('entrada_despues_cierre')) {
            return redirect()->route('kiosko.regreso');
        }

        KioskoState::put(['tipo' => $tipo]);

        $etiquetas = [
            'entrada' => 'Foto de entrada',
            'salida' => 'Foto de salida',
            'salida_ocasional' => 'Foto de salida ocasional',
            'regreso' => 'Foto de regreso',
            'entrada_jornada_1' => 'Foto de entrada jornada 1',
            'salida_jornada_1' => 'Foto de salida jornada 1',
            'entrada_jornada_2' => 'Foto de entrada jornada 2',
            'salida_jornada_2' => 'Foto de salida jornada 2',
        ];

        $campo = KioskoState::get('campo');

        return view('kiosko.camara', [
            'tipo' => $tipo,
            'etiqueta' => $etiquetas[$campo ?? $tipo] ?? $etiquetas[$tipo],
        ]);
    }

    public function guardar(Request $request, AccesoService $acceso): RedirectResponse
    {
        $empleado = KioskoState::empleado();
        $tipo = KioskoState::get('tipo');

        if (! $empleado || ! in_array($tipo, ['entrada', 'salida', 'salida_ocasional', 'regreso'], true)) {
            return redirect()->route('kiosko.cedula');
        }

        $request->validate([
            'foto' => ['nullable', 'string'],
        ]);

        $confirm = $acceso->registrar([
            'empleado_id' => $empleado['id'],
            'user_id' => $empleado['user_id'] ?? null,
            'tipo' => $tipo,
            'campo' => KioskoState::get('campo'),
            'foto' => $request->input('foto'),
            'motivo_texto' => KioskoState::get('motivo_texto'),
            'permiso_id' => KioskoState::get('permiso_id'),
            'hora_regreso' => KioskoState::get('hora_regreso'),
        ]);

        KioskoState::put(['confirm' => $confirm]);

        return redirect()->route('kiosko.confirmacion');
    }

    public function confirmacion(): View|RedirectResponse
    {
        $confirm = KioskoState::get('confirm');

        if (! $confirm) {
            return redirect()->route('kiosko.cedula');
        }

        return view('kiosko.confirmacion', [
            'empleado' => KioskoState::empleado(),
            'confirm' => $confirm,
            'autoMs' => (int) config('acceso.auto_return_seconds') * 1000,
            'hoy' => now()->locale('es')->isoFormat('dddd D [de] MMMM'),
        ]);
    }
}
