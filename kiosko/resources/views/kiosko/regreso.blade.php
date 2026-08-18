@extends('layouts.kiosko')

@section('title', 'Salida ocasional abierta')
@section('screen_class', 'screen-center')

@section('content')
<div class="return-wrap">
    <x-kiosko.foto :src="$empleado['foto'] ?? null" size="sm" />
    <div>
        <div class="badge badge-warn">SALIDA OCASIONAL ABIERTA</div>
        <h1 class="return-title">Tienes una salida ocasional abierta</h1>
        <p class="return-lead">
            @if (! empty($openExit['today']))
                Salida registrada hoy a las <strong>{{ $openExit['time'] ?? '' }}</strong>
            @else
                Salida registrada el <strong>{{ $openExit['date'] ?? '' }}</strong> a las <strong>{{ $openExit['time'] ?? '' }}</strong>
            @endif
            @if (! empty($openExit['reason']))
                por <strong>{{ $openExit['reason'] }}</strong>.
            @endif
            Regreso esperado: <strong>{{ $openExit['back'] ?? '' }}</strong>.
        </p>
        <p class="return-lead">Al confirmar se cierra esta salida. Después podrás elegir si registras una entrada.</p>
        <div class="return-actions">
            <form method="POST" action="{{ route('kiosko.regreso.confirmar') }}">
                @csrf
                <button type="submit" class="primary primary-green primary-tall">Cerrar salida</button>
            </form>
            <a href="{{ route('kiosko.cancelar') }}" class="ghost ghost-link ghost-tall">Cancelar</a>
        </div>
    </div>
</div>
@endsection
