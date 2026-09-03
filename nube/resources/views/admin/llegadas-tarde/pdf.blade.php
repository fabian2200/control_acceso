<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de llegadas tarde · {{ $mesLabel }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #64748b; margin: 0 0 14px; font-size: 10px; }
        .kpis { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .kpis td { border: 1px solid #e2e8f0; padding: 8px 10px; width: 16.66%; }
        .kpis span { display: block; color: #64748b; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; }
        .kpis strong { display: block; font-size: 16px; margin-top: 4px; }
        table.detalle { width: 100%; border-collapse: collapse; }
        table.detalle th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 6px 5px; }
        table.detalle td { border-bottom: 1px solid #e2e8f0; padding: 6px 5px; vertical-align: top; }
        .sin { color: #9f1239; font-weight: 700; }
        .novedad { color: #15803d; font-weight: 700; }
        .permiso { color: #b45309; font-weight: 700; }
        .incompleta { color: #1d4ed8; font-weight: 700; }
        .empty { color: #64748b; }
        .muted { color: #64748b; font-size: 9px; }
    </style>
</head>
<body>
    <h1>Informe de llegadas tarde</h1>
    <p class="meta">{{ $mesLabel }} · {{ $empleadoNombre }} · generado {{ $generado }}</p>

    <table class="kpis">
        <tr>
            <td><span>Llegadas tarde</span><strong>{{ $kpis['total'] }}</strong></td>
            <td><span>Justificadas</span><strong>{{ $kpis['justificadas'] }}</strong></td>
            <td><span>Sin justificar</span><strong>{{ $kpis['sin'] }}</strong></td>
            <td><span>Incompletas</span><strong>{{ $kpis['incompletas'] }}</strong></td>
            <td><span>Tiempo acumulado</span><strong>{{ \App\Services\LlegadaTardeService::minutosLabel($kpis['minutos']) }}</strong></td>
            <td><span>Empleados</span><strong>{{ $kpis['empleados'] }}</strong></td>
        </tr>
    </table>

    @if (empty($filas))
        <p class="empty">No hay llegadas tarde ni marcaciones incompletas con ese filtro.</p>
    @else
        <table class="detalle">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Horario</th>
                    <th>Día</th>
                    <th>Entrada</th>
                    <th>Marcó</th>
                    <th>Tarde</th>
                    <th>Respaldo</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td>{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['identificacion'] }}</td>
                        <td>{{ $fila['horario'] }}</td>
                        <td>{{ $fila['dia_label'] }}</td>
                        <td>{{ $fila['entrada'] }}</td>
                        <td>{{ $fila['marco'] }}</td>
                        <td>{{ $fila['tarde_label'] }}</td>
                        <td class="{{ $fila['respaldo'] }}">{{ $fila['respaldo_label'] }}</td>
                        <td>{{ $fila['mensaje'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
