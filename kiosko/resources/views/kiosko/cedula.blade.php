@extends('layouts.kiosko')

@section('title', 'Cédula')
@section('screen_class', 'screen-split')

@section('content')
<form method="POST" action="{{ route('kiosko.identificar') }}" class="screen-split" id="formCedula">
    @csrf
    <input type="hidden" name="cedula" id="cedula" value="{{ old('cedula') }}" maxlength="12">
    <input type="hidden" name="salida_ocasional" id="salida_ocasional" value="0">

    <div class="pane-copy">
        <div>
            <div class="eyebrow">{{ config('acceso.ubicacion') }}</div>
            <h1 class="title" style="margin-top:22px">Ingresa tu cédula</h1>
            <p class="lead" style="margin-top:14px">Número de identificación. Si no la recuerdas, acércate al puesto de recepción.</p>
        </div>
        <div id="cedulaDisplay" class="cedula-display {{ old('cedula') ? '' : 'is-empty' }}">{{ old('cedula') ?: '••••••••' }}</div>
        <div style="min-height:120px">
            @error('cedula')
                <div class="alert-err">
                    <div class="dot"></div>
                    <p>{{ $message }}</p>
                </div>
            @enderror
            <button type="button" id="btnOccasional" class="occ-btn">Registrar salida ocasional</button>
        </div>
    </div>
    <div class="pane-keys">
        <x-kiosko.teclado target="cedula" :ok="true" />
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('js/teclado.js') }}"></script>
<script src="{{ asset('js/cedula.js') }}"></script>
@endpush
