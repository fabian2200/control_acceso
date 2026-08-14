@extends('layouts.admin')

@section('title', $horario->exists ? 'Editar horario' : 'Nuevo horario')
@section('crumb', 'Horarios')
@section('heading', $horario->exists ? 'Editar horario' : 'Nuevo horario')

@section('actions')
    <a href="{{ route('admin.horarios.index') }}" class="btn-ghost">Volver</a>
@endsection

@section('content')
<form method="POST" action="{{ $horario->exists ? route('admin.horarios.actualizar', $horario) : route('admin.horarios.guardar') }}" class="panel">
    @csrf
    @if ($horario->exists)
        @method('PUT')
    @endif

    <div class="form-grid">
        <label>
            Nombre
            <input type="text" name="nombre" value="{{ old('nombre', $horario->nombre) }}" required maxlength="120">
        </label>
        <label>
            Descripción
            <input type="text" name="descripcion" value="{{ old('descripcion', $horario->descripcion) }}" maxlength="255" placeholder="Opcional">
        </label>
        <label class="check check-box">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $horario->activo))>
            Horario activo
        </label>
    </div>

    <div class="days-head">
        <h2>Días de la semana</h2>
        <button type="button" class="btn-ghost" id="copiarLunes">Copiar lunes a lun–vie</button>
    </div>
    <p class="muted days-hint">Cada marca tiene su hora y una gabela en minutos (tolerancia). Déjalo vacío si ese día es descanso.</p>

    <div class="table-wrap">
        <table class="table days-table">
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Entrada mañana</th>
                    <th>Salida mañana</th>
                    <th>Entrada tarde</th>
                    <th>Salida tarde</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $dia => $item)
                    @php $oldItem = old('items.'.$dia, $item); @endphp
                    <tr>
                        <td><strong>{{ $item['nombre'] }}</strong></td>
                        @foreach (['entrada_manana', 'salida_manana', 'entrada_tarde', 'salida_tarde'] as $campo)
                            <td>
                                <div class="slot">
                                    <input type="time" name="items[{{ $dia }}][{{ $campo }}]" value="{{ $oldItem[$campo] ?? '' }}" data-dia="{{ $dia }}" data-campo="{{ $campo }}">
                                    <label class="gabela">
                                        <span>Gabela</span>
                                        <input type="number" min="0" max="180" name="items[{{ $dia }}][gabela_{{ $campo }}]" value="{{ $oldItem['gabela_'.$campo] ?? '' }}" placeholder="0" data-dia="{{ $dia }}" data-campo="gabela_{{ $campo }}">
                                        <em>min</em>
                                    </label>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">{{ $horario->exists ? 'Guardar cambios' : 'Crear horario' }}</button>
        <a href="{{ route('admin.horarios.index') }}" class="btn-ghost">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.getElementById('copiarLunes')?.addEventListener('click', function () {
        ['entrada_manana', 'salida_manana', 'entrada_tarde', 'salida_tarde',
         'gabela_entrada_manana', 'gabela_salida_manana', 'gabela_entrada_tarde', 'gabela_salida_tarde'].forEach(function (campo) {
            const origen = document.querySelector('[data-dia="1"][data-campo="' + campo + '"]');
            if (!origen) return;
            for (let dia = 2; dia <= 5; dia++) {
                const dest = document.querySelector('[data-dia="' + dia + '"][data-campo="' + campo + '"]');
                if (dest) dest.value = origen.value;
            }
        });
    });
</script>
@endpush
