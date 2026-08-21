@extends('layouts.admin')

@section('title', 'Inicio')
@section('crumb', 'Panel')
@section('heading', 'Resumen')

@section('content')
<div class="stat-grid">
    <article class="stat">
        <div class="card-icon"><i class="fas fa-clock"></i></div>
        <div class="kpi-body">
            <span>Horarios</span>
            <strong>{{ $totalHorarios }}</strong>
        </div>
    </article>
    <article class="stat">
        <div class="card-icon"><i class="fas fa-users"></i></div>
        <div class="kpi-body">
            <span>Empleados activos</span>
            <strong>{{ $empleadosActivos }}</strong>
        </div>
    </article>
    <article class="stat">
        <div class="card-icon"><i class="fas fa-user-check"></i></div>
        <div class="kpi-body">
            <span>Con horario</span>
            <strong>{{ $asignados }}</strong>
        </div>
    </article>
    <article class="stat stat-warn">
        <div class="card-icon"><i class="fas fa-user-times"></i></div>
        <div class="kpi-body">
            <span>Sin horario</span>
            <strong>{{ $sinHorario }}</strong>
        </div>
    </article>
</div>

<section class="panel">
    <div class="panel-head">
        <h2>Horarios creados</h2>
        <a href="{{ route('admin.horarios.crear') }}" class="btn-primary"><i class="fas fa-plus"></i> Nuevo horario</a>
    </div>
    @if ($horarios->isEmpty())
        <p class="empty">Aún no hay horarios. Crea el primero para asignarlo a los empleados.</p>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Estado</th>
                    <th>Empleados</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($horarios as $horario)
                    <tr>
                        <td>
                            <strong>{{ $horario->nombre }}</strong>
                            @if ($horario->descripcion)
                                <div class="muted">{{ $horario->descripcion }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="pill {{ $horario->activo ? 'pill-ok' : 'pill-off' }}">
                                {{ $horario->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>{{ $horario->asignaciones_count }}</td>
                        <td class="td-right">
                            <a href="{{ route('admin.horarios.editar', $horario) }}" class="btn-ghost btn-sm"><i class="fas fa-edit"></i> Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
@endsection
