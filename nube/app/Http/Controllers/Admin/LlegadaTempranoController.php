<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LlegadaTempranoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LlegadaTempranoController extends Controller
{
    public function index(Request $request, LlegadaTempranoService $service): View
    {
        $ahora = now('America/Bogota');
        $empleadoId = $request->query('empleado_id');

        return view('admin.llegadas-temprano.index', $service->informe(
            (int) $request->query('anio', $ahora->year),
            (int) $request->query('mes', $ahora->month),
            $empleadoId !== null && $empleadoId !== '' ? (int) $empleadoId : null,
        ));
    }
}
