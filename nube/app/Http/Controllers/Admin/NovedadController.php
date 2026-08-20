<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccesoNovedad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NovedadController extends Controller
{
    public function index(Request $request): View
    {
        $estado = $request->query('estado', 'todos');
        $q = trim((string) $request->query('q', ''));

        $novedades = AccesoNovedad::query()
            ->with(['empleado.cargoRel'])
            ->when($estado === 'pendiente', fn ($qb) => $qb->whereNull('aprobada'))
            ->when($estado === 'aprobada', fn ($qb) => $qb->where('aprobada', 1))
            ->when($estado === 'rechazada', fn ($qb) => $qb->where('aprobada', 0))
            ->when($q !== '', function ($qb) use ($q) {
                $qb->whereHas('empleado', function ($e) use ($q) {
                    $e->where('identificacion', 'like', "%{$q}%")
                        ->orWhere('nombres', 'like', "%{$q}%")
                        ->orWhere('apellidos', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('admin.novedades.index', [
            'novedades' => $novedades,
            'estado' => $estado,
            'q' => $q,
        ]);
    }

    public function aprobar(AccesoNovedad $novedad): RedirectResponse
    {
        $novedad->update(['aprobada' => 1]);

        return back()->with('ok', 'Novedad aprobada.');
    }

    public function rechazar(AccesoNovedad $novedad): RedirectResponse
    {
        $novedad->update(['aprobada' => 0]);

        return back()->with('ok', 'Novedad rechazada.');
    }
}
