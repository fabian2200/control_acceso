@extends('layouts.kiosko')

@section('title', 'Cámara')
@section('screen_class', 'screen-cam cam')

@section('content')
<div class="cam-bg"></div>
<video id="camVideo" class="cam-live" autoplay playsinline muted></video>
<div class="cam-tag">
    <div class="cam-rec"></div>
    <div class="cam-label">{{ $etiqueta }}</div>
</div>
<div class="cam-oval">
    <div id="camCount" class="cam-count">3</div>
</div>
<p class="cam-hint">Mira a la cámara. La captura es automática.</p>
<div id="camFlash" class="cam-flash hidden"></div>
<canvas id="camCanvas" class="hidden"></canvas>
<form method="POST" action="{{ route('kiosko.registrar') }}" id="formFoto">
    @csrf
    <input type="hidden" name="foto" id="foto">
</form>
@endsection

@push('scripts')
<script src="{{ asset('js/camara.js') }}"></script>
@endpush
