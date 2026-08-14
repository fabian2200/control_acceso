@extends('layouts.kiosko')

@section('title', 'Aviso')
@section('screen_class', 'screen-center')

@section('content')
<div class="overdue-card">
    <div class="overdue-head">
        <div class="overdue-dot"></div>
        <div class="eyebrow" style="color:#b45309">Aviso · no bloquea tu registro</div>
    </div>
    <h1 class="overdue-title">
        Tu salida del {{ $overdue['date'] ?? '' }} a las {{ $overdue['time'] ?? '' }} quedó sin registro de regreso.
    </h1>
    <p class="overdue-lead">Será revisada por RRHH. Puedes continuar con tu registro de hoy normalmente.</p>
    <form method="POST" action="{{ route('kiosko.vencida.ack') }}">
        @csrf
        <button type="submit" class="primary primary-amber" style="margin-top:36px">Entendido</button>
    </form>
</div>
@endsection
