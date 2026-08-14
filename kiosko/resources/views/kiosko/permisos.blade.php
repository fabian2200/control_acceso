@extends('layouts.kiosko')

@section('title', 'Permisos de hoy')
@section('screen_class', 'screen-stack')

@section('content')
<div class="eyebrow" style="color:#b45309">Salida ocasional · paso 2 de 3</div>
<h1 class="title" style="margin-top:14px;font-size:42px">Tus permisos de hoy</h1>
<p class="lead" style="margin-top:10px;max-width:640px">Selecciona el permiso con el que sales. El regreso esperado será la hora de fin del permiso.</p>

@if ($permisos->isEmpty())
    <div class="permiso-empty">
        <div class="permiso-empty-title">No tienes permisos activos hoy</div>
        <p>Si necesitas salir, vuelve y elige <strong>Otro</strong> para indicar la hora de regreso.</p>
    </div>
@else
    <div class="permiso-list">
        @foreach ($permisos as $permiso)
            <form method="POST" action="{{ route('kiosko.permisos.elegir') }}">
                @csrf
                <input type="hidden" name="permiso_id" value="{{ $permiso->id }}">
                <button type="submit" class="permiso-card">
                    <div class="permiso-hours">{{ $permiso->horaInicioFmt() }} – {{ $permiso->horaFinFmt() }}</div>
                    <div class="permiso-motivo">{{ $permiso->motivo }}</div>
                    <div class="permiso-meta">
                        @if ($permiso->rangoFechas() !== '')
                            <span>{{ $permiso->rangoFechas() }}</span>
                        @endif
                        <span>Regreso esperado {{ $permiso->horaFinFmt() }}</span>
                    </div>
                </button>
            </form>
        @endforeach
    </div>
@endif

<a href="{{ route('kiosko.motivo') }}" class="ghost ghost-link" style="margin-top:24px;align-self:flex-start">Volver</a>
@endsection
