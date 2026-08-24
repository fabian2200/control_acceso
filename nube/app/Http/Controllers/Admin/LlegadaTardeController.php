<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LlegadaTardeExcelExporter;
use App\Services\LlegadaTardeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LlegadaTardeController extends Controller
{
    public function index(Request $request, LlegadaTardeService $service): View
    {
        return view('admin.llegadas-tarde.index', $this->informe($request, $service));
    }

    public function pdf(Request $request, LlegadaTardeService $service): Response
    {
        $informe = $this->informe($request, $service);
        $mesLabel = LlegadaTardeService::MESES[$informe['mes']].' '.$informe['anio'];
        $empleadoNombre = 'Todos los empleados';
        if ($informe['empleado_id']) {
            $empleadoNombre = $informe['empleados']->firstWhere('id', $informe['empleado_id'])?->nombre_completo
                ?? 'Empleado';
        }

        $pdf = Pdf::loadView('admin.llegadas-tarde.pdf', [
            ...$informe,
            'mesLabel' => $mesLabel,
            'empleadoNombre' => $empleadoNombre,
            'generado' => LlegadaTardeService::fechaHoraLabel(now('America/Bogota')),
        ])->setPaper('a4', 'landscape');

        $archivo = 'asistencia-horaria-'.$informe['anio'].'-'.str_pad((string) $informe['mes'], 2, '0', STR_PAD_LEFT).'.pdf';

        return $pdf->download($archivo);
    }

    public function excel(Request $request, LlegadaTardeService $service, LlegadaTardeExcelExporter $exporter): StreamedResponse
    {
        $ahora = now('America/Bogota');

        return $exporter->download($service->informe(
            (int) $request->query('anio', $ahora->year),
            (int) $request->query('mes', $ahora->month),
            null,
            'todos',
        ));
    }

    private function informe(Request $request, LlegadaTardeService $service): array
    {
        $ahora = now('America/Bogota');
        $empleadoId = $request->query('empleado_id');

        return $service->informe(
            (int) $request->query('anio', $ahora->year),
            (int) $request->query('mes', $ahora->month),
            $empleadoId !== null && $empleadoId !== '' ? (int) $empleadoId : null,
            (string) $request->query('respaldo', 'todos'),
        );
    }
}
