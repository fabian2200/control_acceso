@extends('layouts.kiosko')

@section('title', '¿Registrar entrada?')
@section('screen_class', 'screen-center')

@section('content')
<div class="return-wrap">
    <x-kiosko.foto :src="$empleado['foto'] ?? null" size="sm" />
    <div>
        <div class="badge badge-ok">SALIDA CERRADA</div>
        <h1 class="return-title">¿Deseas registrar entrada?</h1>
        <p class="return-lead">
            La salida ocasional quedó cerrada
            @if (! empty($cierre['time']))
                a las <strong>{{ $cierre['time'] }}</strong>
            @endif.
            Si marcas entrada, se usará la hora actual del kiosko.
        </p>
        <div class="return-actions">
            <form method="POST" action="{{ route('kiosko.entrada.decidir') }}">
                @csrf
                <input type="hidden" name="registrar_entrada" value="si">
                <button type="submit" class="primary primary-green primary-tall">Sí, registrar entrada</button>
            </form>
            <form method="POST" action="{{ route('kiosko.entrada.decidir') }}">
                @csrf
                <input type="hidden" name="registrar_entrada" value="no">
                <button type="submit" class="ghost ghost-link ghost-tall">No, terminar</button>
            </form>
        </div>
    </div>
</div>
@endsection
