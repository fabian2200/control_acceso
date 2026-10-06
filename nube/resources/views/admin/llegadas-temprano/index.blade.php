@extends('layouts.admin')

@section('title', 'Registros de Puntualidad')
@section('crumb', 'Informe')
@section('heading', 'Registros de Puntualidad')

@section('actions')
    <a href="{{ route('admin.llegadas-temprano.pdf', request()->query()) }}" class="btn-primary"><i class="fas fa-file-pdf"></i> Exportar PDF</a>
    <a href="{{ route('admin.llegadas-temprano.excel', request()->only(['anio', 'mes'])) }}" class="btn-ghost btn-success"><i class="fas fa-file-excel"></i> Exportar Excel</a>
@endsection

@section('content')
<div class="kpi-grid">
    <article class="kpi kpi-ok">
        <div class="card-icon"><i class="fas fa-user-check"></i></div>
        <div class="kpi-body">
            <span>Llegadas temprano</span>
            <strong>{{ $kpis['total'] }}</strong>
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
            <h2>Ranking de llegadas temprano</h2>
            <p class="tarde-legend">Los primeros 6 puestos del mes. Quienes tienen la misma cantidad comparten escalón: tres con 7 van juntos en el 1, y el siguiente con 6 es el 2.</p>
        </div>
    </div>

    @if (empty($ranking))
        <p class="empty">Nadie llegó antes de la hora en ese mes.</p>
    @else
        @php
            $porPuesto = collect($ranking)->groupBy('puesto');
            $etiqueta = fn (int $veces) => $veces === 1 ? '1 llegada temprano' : $veces.' llegadas temprano';
        @endphp
        <div class="podio">
            @foreach ([2, 1, 3] as $puesto)
                @if ($porPuesto->has($puesto))
                    <article class="podio-paso is-{{ $puesto }}">
                        <div class="podio-nombres">
                            @foreach ($porPuesto[$puesto] as $fila)
                                <div class="podio-persona {{ ! empty($fila['seleccionado']) ? 'is-sel' : '' }}">
                                    <strong>{{ $fila['nombre_corto'] }}</strong>
                                </div>
                            @endforeach
                        </div>
                        <div class="podio-base" style="background-image: url('{{ asset('images/'.$puesto.'.png') }}')">
                            <span class="podio-veces">{{ $etiqueta((int) $porPuesto[$puesto]->first()['veces']) }}</span>
                        </div>
                    </article>
                @endif
            @endforeach
        </div>
        <div class="podio-lista">
            @foreach ([4, 5, 6] as $puesto)
                @foreach ($porPuesto->get($puesto, []) as $fila)
                    <div class="podio-fila is-{{ $puesto }} {{ ! empty($fila['seleccionado']) ? 'is-sel' : '' }}">
                        <span class="podio-puesto">{{ $puesto }}</span>
                        <span class="podio-quien">
                            <strong>{{ $fila['nombre_corto'] }}</strong>
                        </span>
                        <span class="podio-cedula">
                            <small>Cédula</small>
                            {{ $fila['identificacion'] }}
                        </span>
                        <span class="podio-pill">
                            <b>{{ $fila['veces'] }}</b>
                            <span>Llegadas temprano</span>
                        </span>
                    </div>
                @endforeach
            @endforeach
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
