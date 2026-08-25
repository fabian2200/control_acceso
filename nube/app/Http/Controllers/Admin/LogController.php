<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccesoNovedad;
use App\Models\AccesoRegistro;
use App\Models\AccesoSalidaOcasional;
use App\Models\Empleado;
use App\Services\LlegadaTardeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $empleados = Empleado::query()
            ->activos()
            ->with('cargoRel')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nombres', 'like', '%'.$q.'%')
                        ->orWhere('apellidos', 'like', '%'.$q.'%')
                        ->orWhere('identificacion', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->get();

        return view('admin.logs.index', [
            'empleados' => $empleados,
            'q' => $q,
        ]);
    }

    public function show(Request $request, Empleado $empleado): View
    {
        $ahora = now('America/Bogota');
        $anio = (int) $request->query('anio', $ahora->year);
        $mes = (int) $request->query('mes', $ahora->month);
        $anio = max(2000, $anio);
        $mes = min(12, max(1, $mes));

        $inicioMes = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/Bogota')->startOfMonth();
        $diasDelMes = $inicioMes->daysInMonth;
        $dia = (int) $request->query('dia', 0);
        if ($dia < 0) {
            $dia = 0;
        }
        if ($dia > $diasDelMes) {
            $dia = $diasDelMes;
        }

        if ($dia > 0) {
            $inicio = Carbon::create($anio, $mes, $dia, 0, 0, 0, 'America/Bogota')->startOfDay();
            $fin = $inicio->copy()->endOfDay();
            $periodoLabel = $inicio->format('d').' de '.LlegadaTardeService::MESES[$mes].' '.$anio;
        } else {
            $inicio = $inicioMes;
            $fin = $inicioMes->copy()->endOfMonth();
            $periodoLabel = LlegadaTardeService::MESES[$mes].' '.$anio;
        }

        $registros = AccesoRegistro::query()
            ->where('empleado_id', $empleado->id)
            ->whereDate('fecha', '>=', $inicio->toDateString())
            ->whereDate('fecha', '<=', $fin->toDateString())
            ->orderBy('registrado_en')
            ->get();

        $ocasionales = AccesoSalidaOcasional::query()
            ->where('empleado_id', $empleado->id)
            ->whereBetween('salida_en', [$inicio, $fin])
            ->orderBy('salida_en')
            ->get();

        $novedades = AccesoNovedad::query()
            ->where('empleado_id', $empleado->id)
            ->whereDate('fecha', '>=', $inicio->toDateString())
            ->whereDate('fecha', '<=', $fin->toDateString())
            ->orderBy('fecha')
            ->orderBy('jornada')
            ->get();

        $items = $this->timeline($registros, $ocasionales, $novedades);
        $service = app(LlegadaTardeService::class);

        return view('admin.logs.show', [
            'empleado' => $empleado->loadMissing('cargoRel'),
            'anio' => $anio,
            'mes' => $mes,
            'dia' => $dia,
            'diasDelMes' => $diasDelMes,
            'meses' => LlegadaTardeService::MESES,
            'anios' => $service->aniosDisponibles(),
            'items' => $items,
            'mesLabel' => $periodoLabel,
        ]);
    }

    /**
     * @return list<array{cuando:Carbon,tipo:string,titulo:string,detalle:string,alerta:bool,foto:?string}>
     */
    private function timeline($registros, $ocasionales, $novedades): array
    {
        $items = [];

        foreach ($registros as $row) {
            $cuando = $row->registrado_en instanceof Carbon
                ? $row->registrado_en
                : Carbon::parse((string) $row->registrado_en, 'America/Bogota');
            $tarde = (int) $row->llego_tarde + (int) $row->salio_tarde;
            $esperada = LlegadaTardeService::horaLabel($row->hora_esperada);
            $items[] = [
                'cuando' => $cuando,
                'tipo' => (string) $row->tipo,
                'titulo' => $row->tipo === 'salida' ? 'Salida' : 'Entrada',
                'detalle' => implode(' · ', array_filter([
                    LlegadaTardeService::horaLabel($row->hora),
                    $esperada !== '—' ? 'esperada '.$esperada : null,
                    $tarde > 0 ? 'tarde '.LlegadaTardeService::minutosLabel($tarde) : null,
                ])),
                'alerta' => $tarde > 0,
                'foto' => $row->fotoSrc(),
            ];
        }

        foreach ($ocasionales as $row) {
            if ($row->salida_en) {
                $cuando = $row->salida_en instanceof Carbon ? $row->salida_en : Carbon::parse((string) $row->salida_en);
                $items[] = [
                    'cuando' => $cuando,
                    'tipo' => 'salida_ocasional',
                    'titulo' => 'Salida ocasional',
                    'detalle' => implode(' · ', array_filter([
                        LlegadaTardeService::horaLabel($cuando),
                        $row->motivo_texto ?: null,
                        $row->autorizado_por ? 'autorizado por '.$row->autorizado_por : null,
                        LlegadaTardeService::horaLabel($row->hora_regreso_esperada) !== '—'
                            ? 'regreso '.LlegadaTardeService::horaLabel($row->hora_regreso_esperada)
                            : null,
                    ])),
                    'alerta' => false,
                    'foto' => $row->fotoSalidaSrc(),
                ];
            }
            if ($row->regreso_en) {
                $cuando = $row->regreso_en instanceof Carbon ? $row->regreso_en : Carbon::parse((string) $row->regreso_en);
                $tarde = (int) $row->minutos_tarde;
                $items[] = [
                    'cuando' => $cuando,
                    'tipo' => 'regreso',
                    'titulo' => 'Regreso Salida Ocasional',
                    'detalle' => implode(' · ', array_filter([
                        LlegadaTardeService::horaLabel($cuando),
                        $tarde > 0 ? 'tarde '.LlegadaTardeService::minutosLabel($tarde) : null,
                    ])),
                    'alerta' => $tarde > 0,
                    'foto' => $row->fotoRegresoSrc(),
                ];
            }
        }

        foreach ($novedades as $row) {
            $fecha = $row->fecha instanceof Carbon ? $row->fecha : Carbon::parse((string) $row->fecha);
            $hora = $this->hhmm($row->hora_inicio_jornada);
            $cuando = Carbon::parse($fecha->toDateString().' '.($hora === '—' ? '00:00' : $hora), 'America/Bogota');
            $items[] = [
                'cuando' => $cuando,
                'tipo' => 'novedad',
                'titulo' => 'Novedad',
                'detalle' => implode(' · ', array_filter([
                    'Jornada '.$row->jornada,
                    $row->motivo,
                    $row->quien_autoriza ? 'autoriza '.$row->quien_autoriza : null,
                    $row->estadoEtiqueta(),
                ])),
                'alerta' => $row->aprobada === 0,
                'foto' => null,
            ];
        }

        usort($items, fn ($a, $b) => $a['cuando'] <=> $b['cuando']);

        return $items;
    }

    private function hhmm(mixed $valor): string
    {
        if ($valor instanceof Carbon) {
            return $valor->format('H:i');
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '—';
        }

        return substr($texto, 0, 5);
    }
}
