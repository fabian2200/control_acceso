@extends('layouts.kiosko')

@section('title', 'Registro listo')
@section('screen_class', 'screen-center')

@section('content')
<div class="confirm-wrap">
    <div class="confirm-ring">
        <svg width="180" height="180" viewBox="0 0 120 120" aria-hidden="true">
            <circle cx="60" cy="60" r="48" fill="none" stroke="{{ $confirm['color'] }}" stroke-width="6" stroke-linecap="round" stroke-dasharray="302" class="confirm-circle"></circle>
            <path d="M39 61 L54 76 L82 46" fill="none" stroke="{{ $confirm['color'] }}" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="70" class="confirm-check"></path>
        </svg>
    </div>
    <div class="confirm-copy">
        <div class="eyebrow">{{ $empleado['nombre'] }} · {{ $hoy }}</div>
        <h1 class="confirm-title">{{ $confirm['title'] }}</h1>
        <div class="confirm-time">{{ $confirm['time'] }}</div>
        <div class="confirm-pills">
            @if (!empty($confirm['pill']))
                <div class="confirm-pill" style="background:{{ $confirm['pill']['bg'] }};color:{{ $confirm['pill']['fg'] }}">{{ $confirm['pill']['text'] }}</div>
            @endif
            @if (!empty($confirm['meta']))
                <div class="confirm-meta">{{ $confirm['meta'] }}</div>
            @endif
        </div>
        <p class="confirm-note">Registro guardado en este kiosko. Se enviará a la NUBE cuando haya conexión.</p>
        <div class="confirm-bar-track">
            <div class="confirm-bar" style="animation:bar {{ $autoMs }}ms linear both"></div>
        </div>
        <p class="confirm-back">Volviendo al teclado…</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    setTimeout(function () {
        window.location.href = @json(route('kiosko.cancelar'));
    }, {{ (int) $autoMs }});
</script>
@endpush
