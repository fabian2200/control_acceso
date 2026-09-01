@extends('layouts.admin')

@section('title', $festivo->exists ? 'Editar festivo' : 'Nuevo festivo')
@section('crumb', 'Festivos')
@section('heading', $festivo->exists ? 'Editar festivo' : 'Nuevo festivo')

@section('actions')
    <a href="{{ route('admin.festivos.index', ['anio' => $anio]) }}" class="btn-ghost"><i class="fas fa-arrow-left"></i> Volver</a>
@endsection

@section('content')
<form method="POST" action="{{ $festivo->exists ? route('admin.festivos.actualizar', $festivo) : route('admin.festivos.guardar') }}" class="panel">
    @csrf
    @if ($festivo->exists)
        @method('PUT')
    @endif

    <p class="muted days-hint">En esta fecha los informes no reportarán llegadas tarde, salidas temprano, marcación incompleta ni regresos tarde de salidas ocasionales.</p>

    <div class="form-grid">
        <label>
            Fecha
            <input type="date" name="fecha" value="{{ old('fecha', optional($festivo->fecha)->format('Y-m-d')) }}" required>
        </label>
        <label>
            Nombre
            <input type="text" name="nombre" value="{{ old('nombre', $festivo->nombre) }}" required maxlength="120" placeholder="Ej. Navidad">
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> {{ $festivo->exists ? 'Guardar cambios' : 'Registrar festivo' }}</button>
        <a href="{{ route('admin.festivos.index', ['anio' => $anio]) }}" class="btn-ghost"><i class="fas fa-times"></i> Cancelar</a>
    </div>
</form>
@endsection
