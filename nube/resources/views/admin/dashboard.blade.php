@extends('layouts.admin')

@section('title', 'Inicio')
@section('crumb', 'Panel')
@section('heading', 'Resumen')

@section('content')
<div class="stat-grid">
    <article class="stat">
        <span>Horarios</span>
        <strong>{{ $totalHorarios }}</strong>
    </article>
    <article class="stat">
        <span>Empleados activos</span>
        <strong>{{ $empleadosActivos }}</strong>
    </article>
    <article class="stat">
        <span>Con horario</span>
        <strong>{{ $asignados }}</strong>
    </article>
    <article class="stat stat-warn">
        <span>Sin horario</span>
        <strong>{{ $sinHorario }}</strong>
    </article>
</div>

<section class="panel">
    <div class="panel-head">
        <h2>Horarios creados</h2>
        <a href="{{ route('admin.horarios.crear') }}" class="btn-primary">Nuevo horario</a>
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
                            <a href="{{ route('admin.horarios.editar', $horario) }}">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
@endsection
