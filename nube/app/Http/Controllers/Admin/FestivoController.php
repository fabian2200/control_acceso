<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccesoFestivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FestivoController extends Controller
{
    public function index(Request $request): View
    {
        $anio = (int) $request->integer('anio', now('America/Bogota')->year);
        $anio = max(2000, min(2100, $anio));

        $festivos = AccesoFestivo::query()
            ->whereYear('fecha', $anio)
            ->orderBy('fecha')
            ->get();

        $anios = $this->aniosDisponibles($anio);

        return view('admin.festivos.index', compact('festivos', 'anio', 'anios'));
    }

    public function crear(Request $request): View
    {
        $anio = (int) $request->integer('anio', now('America/Bogota')->year);

        return view('admin.festivos.form', [
            'festivo' => new AccesoFestivo([
                'fecha' => now('America/Bogota')->toDateString(),
            ]),
            'anio' => $anio,
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        AccesoFestivo::query()->create($data);

        return redirect()
            ->route('admin.festivos.index', ['anio' => substr($data['fecha'], 0, 4)])
            ->with('ok', 'Festivo registrado. Los informes no lo tendrán en cuenta.');
    }

    public function editar(Request $request, AccesoFestivo $festivo): View
    {
        return view('admin.festivos.form', [
            'festivo' => $festivo,
            'anio' => (int) $request->integer('anio', $festivo->fecha->year),
        ]);
    }

    public function actualizar(Request $request, AccesoFestivo $festivo): RedirectResponse
    {
        $data = $this->validar($request, $festivo);
        $festivo->update($data);

        return redirect()
            ->route('admin.festivos.index', ['anio' => substr($data['fecha'], 0, 4)])
            ->with('ok', 'Festivo actualizado.');
    }

    public function eliminar(Request $request, AccesoFestivo $festivo): RedirectResponse
    {
        $anio = $festivo->fecha->year;
        $festivo->delete();

        return redirect()
            ->route('admin.festivos.index', ['anio' => $request->integer('anio', $anio)])
            ->with('ok', 'Festivo eliminado.');
    }

    private function validar(Request $request, ?AccesoFestivo $festivo = null): array
    {
        return $request->validate([
            'fecha' => [
                'required',
                'date',
                Rule::unique('acceso_festivos', 'fecha')->ignore($festivo?->id),
            ],
            'nombre' => ['required', 'string', 'max:120'],
        ]);
    }

    /**
     * @return list<int>
     */
    private function aniosDisponibles(int $anioActual): array
    {
        $min = AccesoFestivo::query()->min('fecha');
        $desde = $min ? (int) substr((string) $min, 0, 4) : $anioActual;
        $hasta = max($anioActual, (int) now('America/Bogota')->year) + 1;

        return range(min($desde, $anioActual), $hasta);
    }
}
