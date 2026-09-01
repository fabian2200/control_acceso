@extends('layouts.admin')

@section('title', 'Salidas ocasionales')
@section('crumb', 'Informe')
@section('heading', 'Salidas ocasionales')

@section('actions')
    <a href="{{ route('admin.salidas-ocasionales.pdf', request()->query()) }}" class="btn-primary"><i class="fas fa-file-pdf"></i> Exportar PDF</a>
    <a href="{{ route('admin.salidas-ocasionales.excel', request()->only(['anio', 'mes'])) }}" class="btn-ghost btn-success"><i class="fas fa-file-excel"></i> Exportar Excel</a>
@endsection

@section('content')
<div class="kpi-grid">
    <article class="kpi kpi-temprano">
        <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="kpi-body">
            <span>Regresos tarde</span>
            <strong>{{ $kpis['total'] }}</strong>
        </div>
    </article>
    <article class="kpi">
        <div class="card-icon"><i class="fas fa-clock"></i></div>
        <div class="kpi-body">
            <span>Retraso acumulado</span>
            <strong>{{ \App\Services\LlegadaTardeService::minutosLabel($kpis['minutos']) }}</strong>
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

<section class="panel tarde-panel">
    <div class="panel-head tarde-head">
        <div>
            <h2>Regresos tarde</h2>
            <p class="tarde-legend">
                Solo salidas ocasionales que regresaron después de la hora pactada.
                <em class="lg-diligencia">violeta</em>: diligencia.
                <em class="lg-permiso">ámbar</em>: permiso.
                <em class="lg-incompleta">índigo</em>: otro motivo.
                Los festivos configurados no se incluyen.
            </p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.salidas-ocasionales.index') }}" class="tarde-filters" id="formOcasional">
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
        <input type="hidden" name="motivo" value="{{ $motivo }}">
    </form>

    <div class="tarde-chips">
        @php
            $base = request()->except('motivo');
        @endphp
        <a class="chip {{ $motivo === 'diligencia' ? 'is-on chip-diligencia' : '' }}" href="{{ route('admin.salidas-ocasionales.index', array_merge($base, ['motivo' => $motivo === 'diligencia' ? 'todos' : 'diligencia'])) }}"><i class="fas fa-briefcase"></i> Diligencia</a>
        <a class="chip {{ $motivo === 'permiso' ? 'is-on chip-permiso' : '' }}" href="{{ route('admin.salidas-ocasionales.index', array_merge($base, ['motivo' => $motivo === 'permiso' ? 'todos' : 'permiso'])) }}"><i class="fas fa-id-card"></i> Permiso</a>
        <a class="chip {{ $motivo === 'ocasional' ? 'is-on chip-incompleta' : '' }}" href="{{ route('admin.salidas-ocasionales.index', array_merge($base, ['motivo' => $motivo === 'ocasional' ? 'todos' : 'ocasional'])) }}"><i class="fas fa-walking"></i> Otro motivo</a>
        <button type="button" class="chip" id="btnExpandir"><i class="fas fa-expand-alt"></i> Desplegar todo</button>
    </div>

    @if (empty($filas))
        <p class="empty">No hay regresos tarde de salidas ocasionales con ese filtro.</p>
    @else
        <div class="tarde-table-head ocasional-table-head">
            <span>Empleado</span>
            <span>Día</span>
            <span>Salió</span>
            <span>Esperado</span>
            <span>Regresó</span>
            <span>Retraso</span>
        </div>
        <div class="tarde-list" id="listaOcasional">
            @foreach ($filas as $fila)
                <details class="tarde-row is-tarde is-motivo-{{ $fila['motivo_tipo'] }}">
                    <summary>
                        <span class="tarde-emp">
                            <span class="card-icon tarde-row-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </span>
                            <span>
                                <strong>{{ $fila['nombre'] }}</strong>
                                <small>{{ $fila['identificacion'] }}</small>
                            </span>
                        </span>
                        <span>{{ $fila['dia_label'] }}</span>
                        <span>{{ $fila['salio'] }}</span>
                        <span>{{ $fila['esperado'] }}</span>
                        <span>{{ $fila['regreso'] }}</span>
                        <span class="tarde-badges">
                            <span class="tarde-temprano">{{ $fila['cumplimiento_label'] }}</span>
                            <span class="pill pill-{{ $fila['motivo_tipo'] }}">{{ $fila['motivo_label'] }}@if (($fila['motivo_tipo'] ?? '') === 'permiso' && ! empty($fila['permiso_intervalo'])) · {{ $fila['permiso_intervalo'] }}@endif</span>
                        </span>
                    </summary>
                    <div class="tarde-detail">
                        <p class="tarde-detail-title">{{ $fila['titulo_detalle'] }} — {{ $fila['mensaje'] }}</p>
                        <dl class="tarde-dl">
                            <div>
                                <dt>Salió</dt>
                                <dd>{{ $fila['salio'] }}</dd>
                            </div>
                            <div>
                                <dt>Regreso esperado</dt>
                                <dd>{{ $fila['esperado'] }}</dd>
                            </div>
                            <div>
                                <dt>Regresó</dt>
                                <dd>{{ $fila['regreso'] }}</dd>
                            </div>
                            <div>
                                <dt>Cargo</dt>
                                <dd>{{ $fila['cargo'] }}</dd>
                            </div>
                            <div>
                                <dt>Horario</dt>
                                <dd>{{ $fila['horario'] }}</dd>
                            </div>
                        </dl>
                        <p class="muted tarde-pie">{{ $fila['pie'] }}</p>
                    </div>
                </details>
            @endforeach
        </div>
    @endif
</section>
@endsection

@push('scripts')
<script>
    const btn = document.getElementById('btnExpandir');
    const lista = document.getElementById('listaOcasional');
    btn?.addEventListener('click', function () {
        const rows = lista.querySelectorAll('details');
        const abrir = [...rows].some((el) => !el.open);
        rows.forEach((el) => { el.open = abrir; });
        btn.innerHTML = abrir
            ? '<i class="fas fa-compress-alt"></i> Contraer todo'
            : '<i class="fas fa-expand-alt"></i> Desplegar todo';
    });
    document.getElementById('formOcasional')?.querySelectorAll('select').forEach((el) => {
        el.addEventListener('change', () => el.form.submit());
    });
</script>
@endpush
