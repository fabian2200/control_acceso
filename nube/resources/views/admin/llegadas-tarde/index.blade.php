@extends('layouts.admin')

@section('title', 'Llegadas tarde')
@section('crumb', 'Informe')
@section('heading', 'Informe de llegadas tarde')

@section('actions')
    <a href="{{ route('admin.llegadas-tarde.pdf', request()->query()) }}" class="btn-primary"><i class="fas fa-file-pdf"></i> Exportar PDF</a>
    <a href="{{ route('admin.llegadas-tarde.excel', request()->only(['anio', 'mes'])) }}" class="btn-ghost btn-success"><i class="fas fa-file-excel"></i> Exportar Excel</a>
@endsection

@section('content')
<div class="kpi-grid kpi-grid-6">
    <article class="kpi">
        <div class="card-icon"><i class="fas fa-clock"></i></div>
        <div class="kpi-body">
            <span>Llegadas tarde</span>
            <strong>{{ $kpis['total'] }}</strong>
        </div>
    </article>
    <article class="kpi">
        <div class="card-icon"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-body">
            <span>Justificadas</span>
            <strong>{{ $kpis['justificadas'] }}</strong>
        </div>
    </article>
    <article class="kpi kpi-alert">
        <div class="card-icon"><i class="fas fa-times-circle"></i></div>
        <div class="kpi-body">
            <span>Sin justificar</span>
            <strong>{{ $kpis['sin'] }}</strong>
        </div>
    </article>
    <article class="kpi kpi-incompleta">
        <div class="card-icon"><i class="fas fa-minus-circle"></i></div>
        <div class="kpi-body">
            <span>Marc. incompleta</span>
            <strong>{{ $kpis['incompletas'] }}</strong>
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
            <h2>Detalle de llegadas</h2>
            <p class="tarde-legend">
                Franja <em class="lg-novedad">verde</em>: novedad.
                <em class="lg-permiso">ámbar</em>: permiso.
                <em class="lg-sin">granate</em>: tarde sin respaldo.
                <em class="lg-incompleta">azul</em>: no marcó la entrada.
            </p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.llegadas-tarde.index') }}" class="tarde-filters" id="formTarde">
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
        <input type="hidden" name="respaldo" value="{{ $respaldo }}">
    </form>

    <div class="tarde-chips">
        @php
            $base = request()->except('respaldo');
        @endphp
        <a class="chip {{ $respaldo === 'sin' ? 'is-on chip-sin' : '' }}" href="{{ route('admin.llegadas-tarde.index', array_merge($base, ['respaldo' => $respaldo === 'sin' ? 'todos' : 'sin'])) }}"><i class="fas fa-times-circle"></i> Sin justificar</a>
        <a class="chip {{ $respaldo === 'novedad' ? 'is-on chip-novedad' : '' }}" href="{{ route('admin.llegadas-tarde.index', array_merge($base, ['respaldo' => $respaldo === 'novedad' ? 'todos' : 'novedad'])) }}"><i class="fas fa-clipboard"></i> Con novedad</a>
        <a class="chip {{ $respaldo === 'permiso' ? 'is-on chip-permiso' : '' }}" href="{{ route('admin.llegadas-tarde.index', array_merge($base, ['respaldo' => $respaldo === 'permiso' ? 'todos' : 'permiso'])) }}"><i class="fas fa-id-card"></i> Con permiso</a>
        <a class="chip {{ $respaldo === 'incompleta' ? 'is-on chip-incompleta' : '' }}" href="{{ route('admin.llegadas-tarde.index', array_merge($base, ['respaldo' => $respaldo === 'incompleta' ? 'todos' : 'incompleta'])) }}"><i class="fas fa-minus-circle"></i> Marcación incompleta</a>
        <button type="button" class="chip" id="btnExpandir"><i class="fas fa-expand-alt"></i> Desplegar todo</button>
    </div>

    @if (empty($filas))
        <p class="empty">No hay llegadas tarde ni marcaciones incompletas con ese filtro.</p>
    @else
        <div class="tarde-table-head">
            <span>Empleado</span>
            <span>Día</span>
            <span>Entrada</span>
            <span>Marcó</span>
            <span>Tarde</span>
            <span>Respaldo</span>
        </div>
        <div class="tarde-list" id="listaTarde">
            @foreach ($filas as $fila)
                <details class="tarde-row is-{{ $fila['respaldo'] }}">
                    <summary>
                        <span class="tarde-emp">
                            <span class="card-icon tarde-row-icon">
                                <i class="fas {{ match ($fila['respaldo']) {
                                    'novedad' => 'fa-clipboard',
                                    'permiso' => 'fa-id-card',
                                    'incompleta' => 'fa-minus-circle',
                                    default => 'fa-exclamation-circle',
                                } }}"></i>
                            </span>
                            <span>
                                <strong>{{ $fila['nombre'] }}</strong>
                                <small>{{ $fila['identificacion'] }}</small>
                            </span>
                        </span>
                        <span>{{ $fila['dia_label'] }}</span>
                        <span>{{ $fila['entrada'] }}</span>
                        <span>{{ $fila['marco'] }}</span>
                        <span class="{{ ($fila['tipo'] ?? '') === 'incompleta' ? 'tarde-incomp' : 'tarde-mins' }}">{{ $fila['tarde_label'] }}</span>
                        <span class="tarde-badges">
                            <span class="pill pill-horario">{{ $fila['horario'] }}</span>
                            <span class="pill pill-{{ $fila['respaldo'] }}">{{ $fila['respaldo_label'] }}</span>
                        </span>
                    </summary>
                    <div class="tarde-detail">
                        <p class="tarde-detail-title">{{ $fila['titulo_detalle'] }} — {{ $fila['mensaje'] }}</p>
                        <dl class="tarde-dl">
                            <div>
                                <dt>Debía entrar</dt>
                                <dd>{{ $fila['entrada'] }}</dd>
                            </div>
                            <div>
                                <dt>Marcó</dt>
                                <dd>{{ $fila['marco'] }}</dd>
                            </div>
                            <div>
                                <dt>Retraso</dt>
                                <dd>{{ $fila['tarde_label'] }}</dd>
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
    const lista = document.getElementById('listaTarde');
    btn?.addEventListener('click', function () {
        const rows = lista.querySelectorAll('details');
        const abrir = [...rows].some((el) => !el.open);
        rows.forEach((el) => { el.open = abrir; });
        btn.innerHTML = abrir
            ? '<i class="fas fa-compress-alt"></i> Contraer todo'
            : '<i class="fas fa-expand-alt"></i> Desplegar todo';
    });
    document.getElementById('formTarde')?.querySelectorAll('select').forEach((el) => {
        el.addEventListener('change', () => el.form.submit());
    });
</script>
@endpush
