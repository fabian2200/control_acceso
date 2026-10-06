<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registros de Puntualidad · {{ $mesLabel }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 16px 0 8px; }
        .meta { color: #64748b; margin: 0 0 14px; font-size: 10px; }
        .kpis { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .kpis td { border: 1px solid #e2e8f0; padding: 8px 10px; width: 33.33%; }
        .kpis span { display: block; color: #64748b; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; }
        .kpis strong { display: block; font-size: 16px; margin-top: 4px; }
        table.detalle { width: 100%; border-collapse: collapse; }
        table.detalle th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 6px 5px; }
        table.detalle td { border-bottom: 1px solid #e2e8f0; padding: 6px 5px; vertical-align: top; }
        .temprano { color: #15803d; font-weight: 700; }
        .empty { color: #64748b; }
    </style>
</head>
<body>
    <h1>Registros de Puntualidad</h1>
    <p class="meta">{{ $mesLabel }} · {{ $empleadoNombre }} · generado {{ $generado }}</p>

    <table class="kpis">
        <tr>
            <td><span>Llegadas temprano</span><strong>{{ $kpis['total'] }}</strong></td>
            <td><span>Promedio por marca</span><strong>{{ \App\Services\LlegadaTardeService::minutosLabel($kpis['promedio']) }}</strong></td>
            <td><span>Empleados</span><strong>{{ $kpis['empleados'] }}</strong></td>
        </tr>
    </table>

    <h2>Ranking · 6 con más llegadas temprano</h2>
    @if (empty($ranking))
        <p class="empty">Nadie llegó antes de la hora en ese mes.</p>
    @else
        <table class="detalle">
            <thead>
                <tr>
                    <th>Puesto</th>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Cargo</th>
                    <th>Llegadas temprano</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ranking as $fila)
                    <tr>
                        <td>{{ $fila['puesto'] }}</td>
                        <td>{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['identificacion'] }}</td>
                        <td>{{ $fila['cargo'] }}</td>
                        <td class="temprano">{{ $fila['veces'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Detalle</h2>
    @if (empty($filas))
        <p class="empty">No hay llegadas temprano con ese filtro.</p>
    @else
        <table class="detalle">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Horario</th>
                    <th>Día</th>
                    <th>Jornada</th>
                    <th>Programada</th>
                    <th>Marcó</th>
                    <th>Anticipación</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td>{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['identificacion'] }}</td>
                        <td>{{ $fila['horario'] }}</td>
                        <td>{{ $fila['dia_label'] }}</td>
                        <td>{{ $fila['jornada'] }}</td>
                        <td>{{ $fila['entrada'] }}</td>
                        <td>{{ $fila['marco'] }}</td>
                        <td class="temprano">{{ $fila['temprano_label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
