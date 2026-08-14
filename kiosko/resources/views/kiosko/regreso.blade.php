@extends('layouts.kiosko')

@section('title', 'Confirmar regreso')
@section('screen_class', 'screen-center')

@section('content')
<div class="return-wrap">
    <x-kiosko.foto :src="$empleado['foto'] ?? null" size="sm" />
    <div>
        <div class="badge badge-warn">SALIDA OCASIONAL ABIERTA</div>
        <h1 class="return-title">Confirmar regreso</h1>
        <p class="return-lead">
            Salida registrada hoy a las <strong>{{ $openExit['time'] ?? '' }}</strong>
            por <strong>{{ $openExit['reason'] ?? '' }}</strong>.
            Regreso esperado: {{ $openExit['back'] ?? '' }}.
        </p>
        <div class="return-actions">
            <form method="POST" action="{{ route('kiosko.regreso.confirmar') }}">
                @csrf
                <button type="submit" class="primary primary-green primary-tall">Sí, ya regresé</button>
            </form>
            <a href="{{ route('kiosko.cancelar') }}" class="ghost ghost-link ghost-tall">Cancelar</a>
        </div>
    </div>
</div>
@endsection
