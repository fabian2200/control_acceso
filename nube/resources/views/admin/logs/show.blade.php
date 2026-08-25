@extends('layouts.admin')

@section('title', $empleado->nombre_completo)
@section('crumb', 'Logs')
@section('heading', $empleado->nombre_completo)

@section('actions')
    <a href="{{ route('admin.logs.index') }}" class="btn-ghost"><i class="fas fa-arrow-left"></i> Volver</a>
@endsection

@section('content')
<section class="panel">
    <div class="panel-head log-head">
        <div>
            <h2>Log de {{ $mesLabel }}</h2>
            <p class="muted">C.C. {{ $empleado->identificacion }}@if ($empleado->cargo_nombre) · {{ $empleado->cargo_nombre }}@endif</p>
        </div>
        <form method="GET" action="{{ route('admin.logs.show', $empleado) }}" class="filters log-period">
            <select name="dia" onchange="this.form.submit()">
                <option value="0" @selected($dia === 0)>Todo el mes</option>
                @for ($d = 1; $d <= $diasDelMes; $d++)
                    <option value="{{ $d }}" @selected($dia === $d)>Día {{ str_pad($d, 2, '0', STR_PAD_LEFT) }}</option>
                @endfor
            </select>
            <select name="mes" onchange="this.form.submit()">
                @foreach ($meses as $num => $nombre)
                    <option value="{{ $num }}" @selected($mes === $num)>{{ $nombre }}</option>
                @endforeach
            </select>
            <select name="anio" onchange="this.form.submit()">
                @foreach ($anios as $y)
                    <option value="{{ $y }}" @selected($anio === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if (empty($items))
        <p class="empty">Sin marcas en este periodo.</p>
    @else
        <ul class="log-timeline">
            @foreach ($items as $item)
                <li class="log-item {{ $item['alerta'] ? 'is-alerta' : '' }}">
                    @if (! empty($item['foto']))
                        <button type="button" class="log-foto" data-foto="{{ $item['foto'] }}" title="Ver foto">
                            <img src="{{ $item['foto'] }}" alt="Foto de {{ $item['titulo'] }}">
                        </button>
                    @else
                        <span class="card-icon log-item-icon">
                            <i class="fas {{ match ($item['tipo']) {
                                'entrada' => 'fa-sign-in-alt',
                                'salida' => 'fa-sign-out-alt',
                                'salida_ocasional' => 'fa-walking',
                                'regreso' => 'fa-undo',
                                'novedad' => 'fa-clipboard',
                                default => 'fa-circle',
                            } }}"></i>
                        </span>
                    @endif
                    <time>{{ \App\Services\LlegadaTardeService::fechaHoraLabel($item['cuando'], 'd/m') }}</time>
                    <strong>{{ $item['titulo'] }}</strong>
                    <span>{{ $item['detalle'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
<div class="log-lightbox" id="logLightbox" hidden>
    <button type="button" class="log-lightbox-close" aria-label="Cerrar">&times;</button>
    <img alt="Foto de la marca">
</div>
@endsection

@push('scripts')
<script>
    const box = document.getElementById('logLightbox');
    const img = box?.querySelector('img');
    const close = () => { box.hidden = true; };
    document.querySelectorAll('.log-foto').forEach((el) => {
        el.addEventListener('click', () => {
            img.src = el.dataset.foto;
            box.hidden = false;
        });
    });
    box?.addEventListener('click', (e) => { if (e.target === box) close(); });
    box?.querySelector('.log-lightbox-close')?.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !box.hidden) close(); });
</script>
@endpush
