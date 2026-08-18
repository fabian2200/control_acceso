@extends('layouts.admin')

@section('title', 'Empleados')
@section('crumb', 'Empleados')
@section('heading', 'Asignar horarios')

@section('content')
<section class="panel">
    <form method="GET" action="{{ route('admin.empleados.index') }}" class="filters">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre o cédula">
        <select name="filtro">
            <option value="todos" @selected($filtro === 'todos')>Todos</option>
            <option value="asignados" @selected($filtro === 'asignados')>Con horario</option>
            <option value="sin_horario" @selected($filtro === 'sin_horario')>Sin horario</option>
        </select>
        <button type="submit" class="btn-ghost">Filtrar</button>
    </form>

    @if ($empleados->isEmpty())
        <p class="empty">No hay empleados con ese filtro.</p>
    @else
        <form method="POST" action="{{ route('admin.empleados.asignar-lote') }}" id="formLote">
            @csrf
            <div class="lote-bar">
                <label>
                    Asignar a los seleccionados
                    <select name="horario_id">
                        <option value="">Quitar horario</option>
                        @foreach ($horarios as $horario)
                            <option value="{{ $horario->id }}">{{ $horario->nombre }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn-primary">Aplicar</button>
            </div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="th-check"><input type="checkbox" id="checkAll"></th>
                        <th>Empleado</th>
                        <th>Cédula</th>
                        <th>Cargo</th>
                        <th>Horario</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($empleados as $empleado)
                        <tr>
                            <td>
                                <input type="checkbox" form="formLote" name="empleado_ids[]" value="{{ $empleado->id }}" class="row-check">
                            </td>
                            <td><strong>{{ $empleado->nombre_completo }}</strong></td>
                            <td>{{ $empleado->identificacion }}</td>
                            <td>{{ $empleado->cargo_nombre }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.empleados.asignar', $empleado) }}" class="inline-assign">
                                    @csrf
                                    @method('PUT')
                                    <select name="horario_id">
                                        <option value="">Sin horario</option>
                                        @foreach ($horarios as $horario)
                                            <option value="{{ $horario->id }}" @selected($empleado->asignacionHorario?->horario_id == $horario->id)>
                                                {{ $horario->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-ghost btn-sm">Guardar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection

@push('scripts')
<script>
    const all = document.getElementById('checkAll');
    all?.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(function (el) {
            el.checked = all.checked;
        });
    });
</script>
@endpush
