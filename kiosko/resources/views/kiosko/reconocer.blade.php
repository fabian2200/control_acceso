@extends('layouts.kiosko')

@section('title', 'Identificado')
@section('screen_class', 'screen-center')

@section('content')
<div class="rec-wrap">
    <x-kiosko.foto :src="$empleado['foto']" />
    <div class="rec-copy">
        <div class="badge badge-ok">CÉDULA VERIFICADA</div>
        <h1 class="emp-name">{{ $empleado['nombre'] }}</h1>
        <p class="emp-role">{{ $empleado['cargo'] }} · C.C. {{ $empleado['identificacion'] }}</p>
        <div class="rec-wait">
            <div class="spin"></div>
            Cargando opciones…
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    setTimeout(function () {
        window.location.href = @json($continuarUrl);
    }, 1500);
</script>
@endpush
