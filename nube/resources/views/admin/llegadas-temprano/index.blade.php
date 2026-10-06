@extends('layouts.admin')

@section('title', 'Registros de Puntualidad')
@section('crumb', 'Informe')
@section('heading', 'Registros de Puntualidad')

@section('content')
<div class="kpi-grid kpi-grid-4">
    <article class="kpi kpi-ok">
        <div class="card-icon"><i class="fas fa-user-check"></i></div>
        <div class="kpi-body">
            <span>Llegadas temprano</span>
            <strong>{{ $kpis['total'] }}</strong>
        </div>
    </article>
    <article class="kpi">
        <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="kpi-body">
            <span>Tiempo acumulado</span>
            <strong>{{ \App\Services\LlegadaTardeService::minutosLabel($kpis['minutos']) }}</strong>
        </div>
    </article>
    <article class="kpi">
        <div class="card-icon"><i class="fas fa-stopwatch"></i></div>
        <div class="kpi-body">
            <span>Promedio por marca</span>
            <strong>{{ \App\Services\LlegadaTardeService::minutosLabel($kpis['promedio']) }}</strong>
        </div>
    </article>
    <article class="kpi">
        <div class="card-icon"><i class="fas fa-users"></i></div>
        <div class="kpi-body">
            <span>Empleados</span>
            <strong>{{ $kpis['empleados'] }}</strong>
        </div>
    </article>
</div>

<form method="GET" action="{{ route('admin.llegadas-temprano.index') }}" class="tarde-filters" id="formTemprano">
    <select name="empleado_id">
        <option value="">Todos los empleados</option>
        @foreach ($empleados as $emp)
            <option value="{{ $emp->id }}" @selected($empleado_id === $emp->id)>{{ $emp->nombre_completo }}</option>
        @endforeach
    </select>
    <select name="mes">
        @foreach ($meses as $num => $nombre)
            <option value="{{ $num }}" @selected($mes === $num)>{{ $nombre }}</option>
        @endforeach
    </select>
    <select name="anio">
        @foreach ($anios as $y)
            <option value="{{ $y }}" @selected($anio === $y)>{{ $y }}</option>
        @endforeach
    </select>
</form>

<section class="panel tarde-panel">
    <div class="panel-head">
        <div>
            <h2>Ranking de anticipación</h2>
            <p class="tarde-legend">Ordenado por minutos acumulados antes de la hora de entrada. El puesto es del mes completo; el filtro de empleado solo marca a la persona.</p>
        </div>
    </div>

    @if (empty($ranking))
        <p class="empty">Nadie llegó antes de la hora en ese mes.</p>
    @else
        <div class="table-wrap">
            <table class="table ranking-table">
                <thead>
                    <tr>
                        <th>Puesto</th>
                        <th>Empleado</th>
                        <th>Cédula</th>
                        <th>Veces</th>
                        <th>Acumulado</th>
                        <th>Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ranking as $fila)
                        <tr class="{{ ! empty($fila['seleccionado']) ? 'is-sel' : '' }}">
                            <td>{{ $fila['puesto'] }}</td>
                            <td>
                                <strong>{{ $fila['nombre'] }}</strong>
                                <small class="muted">{{ $fila['cargo'] }}</small>
                            </td>
                            <td>{{ $fila['identificacion'] }}</td>
                            <td>{{ $fila['veces'] }}</td>
                            <td class="tarde-ok">{{ \App\Services\LlegadaTardeService::minutosLabel($fila['minutos']) }}</td>
                            <td>{{ \App\Services\LlegadaTardeService::minutosLabel($fila['promedio']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="panel tarde-panel" style="margin-top:22px">
    <div class="panel-head">
        <div>
            <h2>Detalle de llegadas temprano</h2>
            <p class="tarde-legend">Entradas marcadas antes de la hora de la jornada. Los festivos configurados no se incluyen.</p>
        </div>
    </div>

    @if (empty($filas))
        <p class="empty">No hay llegadas temprano con ese filtro.</p>
    @else
        <div class="tarde-table-head puntual-table-head">
            <span>Empleado</span>
            <span>Día</span>
            <span>Jornada</span>
            <span>Programada</span>
            <span>Marcó</span>
            <span>Anticipación</span>
        </div>
        <div class="tarde-list puntual-list">
            @foreach ($filas as $fila)
                <div class="tarde-row is-puntual">
                    <div class="puntual-row">
                        <span class="tarde-emp">
                            <span class="card-icon tarde-row-icon">
                                <i class="fas fa-user-check"></i>
                            </span>
                            <span>
                                <strong>{{ $fila['nombre'] }}</strong>
                                <small>{{ $fila['identificacion'] }}</small>
                            </span>
                        </span>
                        <span>{{ $fila['dia_label'] }}</span>
                        <span>J{{ $fila['jornada'] }}</span>
                        <span>{{ $fila['entrada'] }}</span>
                        <span>{{ $fila['marco'] }}</span>
                        <span class="tarde-ok">{{ $fila['temprano_label'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endsection

@push('scripts')
<script>
    document.getElementById('formTemprano')?.querySelectorAll('select').forEach((el) => {
        el.addEventListener('change', () => el.form.submit());
    });
</script>
@endpush
