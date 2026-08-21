@extends('layouts.admin')

@section('title', 'Horarios')
@section('crumb', 'Horarios')
@section('heading', 'Horarios')

@section('actions')
    <a href="{{ route('admin.horarios.crear') }}" class="btn-primary"><i class="fas fa-plus"></i> Nuevo horario</a>
@endsection

@section('content')
@if ($horarios->isEmpty())
    <section class="panel">
        <p class="empty">No hay horarios. Crea uno con los días de la semana: jornada 1 (obligatoria si hay trabajo) y jornada 2 (opcional).</p>
    </section>
@else
    <div class="card-grid">
        @foreach ($horarios as $horario)
            <article class="horario-card">
                <div class="horario-card-top">
                    <div class="horario-card-title">
                        <div class="card-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div>
                            <h2>{{ $horario->nombre }}</h2>
                            <p>{{ $horario->descripcion ?: 'Sin descripción' }}</p>
                        </div>
                    </div>
                    <span class="pill {{ $horario->activo ? 'pill-ok' : 'pill-off' }}">
                        {{ $horario->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
                <ul class="dia-list">
                    @foreach ($horario->items as $item)
                        <li>
                            <span>{{ $item->nombreDia() }}</span>
                            <strong>{{ $item->resumen() }}</strong>
                        </li>
                    @endforeach
                </ul>
                <div class="horario-card-foot">
                    <span class="muted"><i class="fas fa-users"></i> {{ $horario->asignaciones_count }} empleados</span>
                    <div class="row-actions">
                        <a href="{{ route('admin.horarios.editar', $horario) }}" class="btn-ghost"><i class="fas fa-edit"></i> Editar</a>
                        <form method="POST" action="{{ route('admin.horarios.eliminar', $horario) }}" onsubmit="return confirm('¿Eliminar este horario?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger-text"><i class="fas fa-trash-alt"></i> Eliminar</button>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endif
@endsection
