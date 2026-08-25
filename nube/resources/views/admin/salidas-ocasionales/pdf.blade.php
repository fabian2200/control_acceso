<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Regresos tarde · salidas ocasionales · {{ $mesLabel }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #64748b; margin: 0 0 14px; font-size: 10px; }
        .kpis { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .kpis td { border: 1px solid #e2e8f0; padding: 8px 10px; width: 33.33%; }
        .kpis span { display: block; color: #64748b; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; }
        .kpis strong { display: block; font-size: 16px; margin-top: 4px; }
        table.detalle { width: 100%; border-collapse: collapse; }
        table.detalle th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 6px 5px; }
        table.detalle td { border-bottom: 1px solid #e2e8f0; padding: 6px 5px; vertical-align: top; }
        .tarde { color: #b45309; font-weight: 700; }
        .empty { color: #64748b; }
    </style>
</head>
<body>
    <h1>Regresos tarde de salidas ocasionales</h1>
    <p class="meta">{{ $mesLabel }} · {{ $empleadoNombre }} · generado {{ $generado }}</p>

    <table class="kpis">
        <tr>
            <td><span>Regresos tarde</span><strong>{{ $kpis['total'] }}</strong></td>
            <td><span>Retraso acumulado</span><strong>{{ \App\Services\LlegadaTardeService::minutosLabel($kpis['minutos']) }}</strong></td>
            <td><span>Empleados</span><strong>{{ $kpis['empleados'] }}</strong></td>
        </tr>
    </table>

    @if (empty($filas))
        <p class="empty">No hay regresos tarde de salidas ocasionales con ese filtro.</p>
    @else
        <table class="detalle">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Día</th>
                    <th>Salió</th>
                    <th>Esperado</th>
                    <th>Regresó</th>
                    <th>Retraso</th>
                    <th>Tipo</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td>{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['identificacion'] }}</td>
                        <td>{{ $fila['dia_label'] }}</td>
                        <td>{{ $fila['salio'] }}</td>
                        <td>{{ $fila['esperado'] }}</td>
                        <td>{{ $fila['regreso'] }}</td>
                        <td class="tarde">{{ $fila['cumplimiento_label'] }}</td>
                        <td>{{ $fila['motivo_label'] }}@if (($fila['motivo_tipo'] ?? '') === 'permiso' && ! empty($fila['permiso_intervalo'])) · {{ $fila['permiso_intervalo'] }}@endif</td>
                        <td>{{ $fila['mensaje'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
