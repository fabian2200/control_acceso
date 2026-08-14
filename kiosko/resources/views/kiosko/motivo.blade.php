@extends('layouts.kiosko')

@section('title', 'Motivo')
@section('screen_class', 'screen-stack')

@section('content')
<div class="eyebrow" style="color:#b45309">Salida ocasional · paso 1 de 3</div>
<h1 class="title" style="margin-top:14px;font-size:42px">Motivo de la salida</h1>
<p class="lead" style="margin-top:12px;max-width:560px">Elige si sales con un permiso aprobado o registra otra salida e indica a qué hora regresas.</p>
<div class="reason-grid">
    <form method="POST" action="{{ route('kiosko.motivo.guardar') }}">
        @csrf
        <input type="hidden" name="origen" value="permiso">
        <button type="submit" class="reason-btn reason-btn-stack">
            <div class="reason-dot"></div>
            <div>
                <span>Permiso</span>
                <small>Usa un permiso aprobado de hoy</small>
            </div>
        </button>
    </form>
    <form method="POST" action="{{ route('kiosko.motivo.guardar') }}">
        @csrf
        <input type="hidden" name="origen" value="otro">
        <button type="submit" class="reason-btn reason-btn-stack">
            <div class="reason-dot"></div>
            <div>
                <span>Otro</span>
                <small>Indica la hora de regreso esperada</small>
            </div>
        </button>
    </form>
</div>
<a href="{{ route('kiosko.accion') }}" class="ghost ghost-link" style="margin-top:30px;align-self:flex-start">Volver</a>
@endsection
