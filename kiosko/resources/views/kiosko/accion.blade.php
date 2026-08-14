@extends('layouts.kiosko')

@section('title', 'Registrar')
@section('screen_class', 'screen-stack')

@section('content')
@php
    $conHorario = collect($botones)->contains(fn ($boton) => ! empty($boton['campo']));
@endphp
<div class="action-head">
    <div>
        <div class="eyebrow">Hola, {{ $empleado['primero'] }}</div>
        <h1 class="title" style="margin-top:12px;font-size:40px">¿Qué vas a registrar?</h1>
    </div>
    <div class="action-clock">
        <div class="clock">{{ $ahora->format('H:i') }}</div>
        <div class="today">{{ $ahora->locale('es')->isoFormat('dddd D [de] MMMM') }}</div>
    </div>
</div>

@if ($errors->any())
    <div class="alert-err" style="margin-top:18px">
        <span class="dot"></span>
        <p>{{ $errors->first() }}</p>
    </div>
@endif

<div class="action-grid {{ $conHorario ? 'action-slots' : '' }}">
    @foreach ($botones as $boton)
        <form method="POST" action="{{ route('kiosko.accion.elegir') }}" @class(['action-occ-span' => $conHorario && $boton['tipo'] === 'salida_ocasional'])>
            @csrf
            <input type="hidden" name="tipo" value="{{ $boton['tipo'] }}">
            @if (! empty($boton['campo']))
                <input type="hidden" name="campo" value="{{ $boton['campo'] }}">
            @endif
            <button type="submit" class="action-card {{ $boton['clase'] }}" @disabled(! $boton['enabled'])>
                <div class="action-dot"></div>
                <div class="action-title">{{ $boton['label'] }}</div>
                <div class="action-sub">{{ $boton['sub'] }}</div>
            </button>
        </form>
    @endforeach
</div>

<a href="{{ route('kiosko.cancelar') }}" class="ghost ghost-link" style="margin-top:26px;align-self:flex-start">No soy {{ $empleado['primero'] }}</a>
@endsection
