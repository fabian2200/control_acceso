<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LlegadaTardeService;
use App\Services\LlegadaTempranoExcelExporter;
use App\Services\LlegadaTempranoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LlegadaTempranoController extends Controller
{
    public function index(Request $request, LlegadaTempranoService $service): View
    {
        return view('admin.llegadas-temprano.index', $this->informe($request, $service));
    }

    public function pdf(Request $request, LlegadaTempranoService $service): Response
    {
        $informe = $this->informe($request, $service);
        $mesLabel = LlegadaTardeService::MESES[$informe['mes']].' '.$informe['anio'];
        $empleadoNombre = 'Todos los empleados';
        if ($informe['empleado_id']) {
            $empleadoNombre = $informe['empleados']->firstWhere('id', $informe['empleado_id'])?->nombre_completo
                ?? 'Empleado';
        }

        $pdf = Pdf::loadView('admin.llegadas-temprano.pdf', [
            ...$informe,
            'mesLabel' => $mesLabel,
            'empleadoNombre' => $empleadoNombre,
            'generado' => LlegadaTardeService::fechaHoraLabel(now('America/Bogota')),
        ])->setPaper('a4', 'landscape');

        $archivo = 'registros-puntualidad-'.$informe['anio'].'-'.str_pad((string) $informe['mes'], 2, '0', STR_PAD_LEFT).'.pdf';

        return $pdf->download($archivo);
    }

    public function excel(Request $request, LlegadaTempranoService $service, LlegadaTempranoExcelExporter $exporter): StreamedResponse
    {
        $ahora = now('America/Bogota');

        return $exporter->download($service->informe(
            (int) $request->query('anio', $ahora->year),
            (int) $request->query('mes', $ahora->month),
            null,
        ));
    }

    private function informe(Request $request, LlegadaTempranoService $service): array
    {
        $ahora = now('America/Bogota');
        $empleadoId = $request->query('empleado_id');

        return $service->informe(
            (int) $request->query('anio', $ahora->year),
            (int) $request->query('mes', $ahora->month),
            $empleadoId !== null && $empleadoId !== '' ? (int) $empleadoId : null,
        );
    }
}
