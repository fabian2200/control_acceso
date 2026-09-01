@extends('layouts.admin')

@section('title', 'Festivos')
@section('crumb', 'Festivos')
@section('heading', 'Días festivos')

@section('actions')
    <a href="{{ route('admin.festivos.crear', ['anio' => $anio]) }}" class="btn-primary"><i class="fas fa-plus"></i> Nuevo festivo</a>
@endsection

@section('content')
<section class="panel">
    <p class="muted days-hint">Estos días no entran en los informes de asistencia horaria ni de salidas ocasionales (pantalla, PDF y Excel). Agrégalos uno a uno.</p>

    <form method="GET" action="{{ route('admin.festivos.index') }}" class="filters">
        <select name="anio" onchange="this.form.submit()">
            @foreach ($anios as $y)
                <option value="{{ $y }}" @selected($anio === $y)>{{ $y }}</option>
            @endforeach
        </select>
    </form>

    @if ($festivos->isEmpty())
        <p class="empty">No hay festivos en {{ $anio }}. Usa «Nuevo festivo» para registrar la fecha y el nombre.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Día</th>
                        <th>Nombre</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($festivos as $festivo)
                        <tr>
                            <td><strong>{{ $festivo->fecha->format('d/m/Y') }}</strong></td>
                            <td>{{ $festivo->diaLabel() }}</td>
                            <td>{{ $festivo->nombre }}</td>
                            <td class="td-right">
                                <div class="row-actions">
                                    <a href="{{ route('admin.festivos.editar', [$festivo, 'anio' => $anio]) }}" class="btn-ghost btn-sm"><i class="fas fa-edit"></i> Editar</a>
                                    <form method="POST" action="{{ route('admin.festivos.eliminar', $festivo) }}" onsubmit="return confirm('¿Eliminar este festivo?')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="anio" value="{{ $anio }}">
                                        <button type="submit" class="btn-danger-text"><i class="fas fa-trash-alt"></i> Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
