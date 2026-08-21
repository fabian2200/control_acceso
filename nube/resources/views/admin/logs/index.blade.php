@extends('layouts.admin')

@section('title', 'Logs')
@section('crumb', 'Logs')
@section('heading', 'Logs de empleados')

@section('content')
<section class="panel">
    <form method="GET" action="{{ route('admin.logs.index') }}" class="filters">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre o cédula">
        <button type="submit" class="btn-ghost"><i class="fas fa-filter"></i> Filtrar</button>
    </form>

    @if ($empleados->isEmpty())
        <p class="empty">No hay empleados con ese filtro.</p>
    @else
        <div class="log-emp-list">
            @foreach ($empleados as $empleado)
                <a class="log-emp-row" href="{{ route('admin.logs.show', $empleado) }}">
                    <div class="log-emp-main">
                        <div class="card-icon"><i class="fas fa-user"></i></div>
                        <div>
                            <strong>{{ $empleado->nombre_completo }}</strong>
                            <div class="muted">C.C. {{ $empleado->identificacion }}@if ($empleado->cargo_nombre) · {{ $empleado->cargo_nombre }}@endif</div>
                        </div>
                    </div>
                    <span class="log-emp-go"><i class="fas fa-chevron-right"></i> Ver log</span>
                </a>
            @endforeach
        </div>
    @endif
</section>
@endsection
