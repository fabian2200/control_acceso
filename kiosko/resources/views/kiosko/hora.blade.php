@extends('layouts.kiosko')

@section('title', 'Hora de regreso')
@section('screen_class', 'screen-split')

@section('content')
<form method="POST" action="{{ route('kiosko.hora.guardar') }}" class="screen-split" id="formHora">
    @csrf
    <input type="hidden" name="hora_regreso" id="hora_regreso" value="{{ $hora }}" maxlength="4">

    <div class="pane-copy">
        <div class="eyebrow" style="color:#b45309">Salida ocasional · paso 2 de 3</div>
        <h1 class="title" style="margin-top:14px;font-size:42px">Hora de regreso esperada</h1>
        <p class="lead" style="margin-top:10px">Motivo: {{ $motivo }}. Indica a qué hora esperas volver.</p>
        <div id="horaDisplay" class="hora-display">__:__</div>
        <div class="quick-row">
            <button type="button" class="quick" data-mins="30">+30 min</button>
            <button type="button" class="quick" data-mins="60">+1 h</button>
            <button type="button" class="quick" data-mins="120">+2 h</button>
        </div>
        <div class="hora-actions">
            <a href="{{ route('kiosko.motivo') }}" class="ghost ghost-link ghost-tall">Volver</a>
            <button type="submit" id="btnContinuarFoto" class="primary" disabled>Continuar a la foto</button>
        </div>
    </div>
    <div class="pane-keys pane-keys-sm">
        <x-kiosko.teclado target="hora_regreso" :compact="true" />
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('js/teclado.js') }}"></script>
<script src="{{ asset('js/hora.js') }}"></script>
@endpush
