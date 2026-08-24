@extends('layouts.admin')

@section('title', 'Novedades')
@section('crumb', 'Novedades')
@section('heading', 'Novedades del kiosko')

@section('content')
<section class="panel">
    <form method="GET" action="{{ route('admin.novedades.index') }}" class="filters">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre o cédula">
        <select name="estado">
            <option value="todos" @selected($estado === 'todos')>Todos</option>
            <option value="pendiente" @selected($estado === 'pendiente')>Pendientes</option>
            <option value="aprobada" @selected($estado === 'aprobada')>Aprobadas</option>
            <option value="rechazada" @selected($estado === 'rechazada')>Rechazadas</option>
        </select>
        <button type="submit" class="btn-ghost"><i class="fas fa-filter"></i> Filtrar</button>
    </form>

    @if ($novedades->isEmpty())
        <p class="empty">No hay novedades con ese filtro.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Empleado</th>
                        <th>Jornada</th>
                        <th>Horario</th>
                        <th>Motivo</th>
                        <th>Autoriza</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($novedades as $novedad)
                        @php
                            $inicio = \App\Services\LlegadaTardeService::horaLabel($novedad->hora_inicio_jornada);
                            $fin = \App\Services\LlegadaTardeService::horaLabel($novedad->hora_fin_jornada);
                            $pill = match ($novedad->aprobada) {
                                1 => 'pill-ok',
                                0 => 'pill-off',
                                default => 'pill-warn',
                            };
                        @endphp
                        <tr>
                            <td>{{ optional($novedad->fecha)?->format('d/m/Y') }}</td>
                            <td>
                                <strong>{{ $novedad->empleado?->nombre_completo ?? '—' }}</strong>
                                <div class="muted">{{ $novedad->empleado?->identificacion }}</div>
                            </td>
                            <td>Jornada {{ $novedad->jornada }}</td>
                            <td>{{ $inicio }} – {{ $fin }}</td>
                            <td>{{ $novedad->motivo }}</td>
                            <td>{{ $novedad->quien_autoriza ?: '—' }}</td>
                            <td>
                                <span class="pill {{ $pill }}">{{ $novedad->estadoEtiqueta() }}</span>
                            </td>
                            <td class="td-right">
                                @if ($novedad->aprobada === null)
                                    <form method="POST" action="{{ route('admin.novedades.aprobar', $novedad) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn-ghost btn-sm"><i class="fas fa-check"></i> Aprobar</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.novedades.rechazar', $novedad) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn-ghost btn-sm"><i class="fas fa-times"></i> Rechazar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
