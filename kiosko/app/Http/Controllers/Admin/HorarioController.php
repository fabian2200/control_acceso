<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccesoHorario;
use App\Models\AccesoHorarioItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HorarioController extends Controller
{
    public function index(): View
    {
        $horarios = AccesoHorario::query()
            ->with(['items'])
            ->withCount('asignaciones')
            ->orderBy('nombre')
            ->get();

        return view('admin.horarios.index', compact('horarios'));
    }

    public function crear(): View
    {
        return view('admin.horarios.form', [
            'horario' => new AccesoHorario(['activo' => true]),
            'items' => $this->itemsVacios(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $horario = AccesoHorario::query()->create($data);
        $this->sincronizarItems($horario, $request->input('items', []));

        return redirect()
            ->route('admin.horarios.index')
            ->with('ok', 'Horario creado.');
    }

    public function editar(AccesoHorario $horario): View
    {
        $horario->load('items');

        return view('admin.horarios.form', [
            'horario' => $horario,
            'items' => $this->itemsDe($horario),
        ]);
    }

    public function actualizar(Request $request, AccesoHorario $horario): RedirectResponse
    {
        $data = $this->validar($request);
        $horario->update($data);
        $this->sincronizarItems($horario, $request->input('items', []));

        return redirect()
            ->route('admin.horarios.index')
            ->with('ok', 'Horario actualizado.');
    }

    public function eliminar(AccesoHorario $horario): RedirectResponse
    {
        if ($horario->asignaciones()->exists()) {
            return back()->withErrors([
                'horario' => 'No se puede eliminar: hay empleados asignados a este horario.',
            ]);
        }

        if ($horario->registros()->exists() || $horario->salidasOcasionales()->exists()) {
            return back()->withErrors([
                'horario' => 'No se puede eliminar: hay marcas o salidas ocasionales asociadas a este horario.',
            ]);
        }

        $horario->delete();

        return redirect()
            ->route('admin.horarios.index')
            ->with('ok', 'Horario eliminado.');
    }

    private function validar(Request $request): array
    {
        $items = $request->input('items', []);

        foreach ($items as $dia => $row) {
            foreach (['entrada_jornada_1', 'salida_jornada_1', 'entrada_jornada_2', 'salida_jornada_2'] as $campo) {
                $valor = trim((string) ($row[$campo] ?? ''));
                if ($valor === '') {
                    $items[$dia][$campo] = null;
                } else {
                    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $valor)) {
                        $valor = substr($valor, 0, 5);
                    }
                    $items[$dia][$campo] = $valor;
                }

                $gabela = $row['gabela_'.$campo] ?? null;
                $items[$dia]['gabela_'.$campo] = ($gabela === null || $gabela === '')
                    ? null
                    : (int) $gabela;
            }
        }

        $request->merge(['items' => $items]);

        $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            'items' => ['required', 'array'],
            'items.*.entrada_jornada_1' => ['nullable', 'date_format:H:i'],
            'items.*.salida_jornada_1' => ['nullable', 'date_format:H:i'],
            'items.*.entrada_jornada_2' => ['nullable', 'date_format:H:i'],
            'items.*.salida_jornada_2' => ['nullable', 'date_format:H:i'],
            'items.*.gabela_entrada_jornada_1' => ['nullable', 'integer', 'min:0', 'max:180'],
            'items.*.gabela_salida_jornada_1' => ['nullable', 'integer', 'min:0', 'max:180'],
            'items.*.gabela_entrada_jornada_2' => ['nullable', 'integer', 'min:0', 'max:180'],
            'items.*.gabela_salida_jornada_2' => ['nullable', 'integer', 'min:0', 'max:180'],
        ], [
            'nombre.required' => 'Indica el nombre del horario.',
        ]);

        return [
            'nombre' => $request->input('nombre'),
            'descripcion' => $request->input('descripcion') ?: null,
            'activo' => $request->boolean('activo'),
        ];
    }

    private function sincronizarItems(AccesoHorario $horario, array $items): void
    {
        foreach (array_keys(AccesoHorarioItem::DIAS) as $dia) {
            $row = $items[$dia] ?? [];

            $horario->items()->updateOrCreate(
                ['dia_semana' => $dia],
                [
                    'entrada_jornada_1' => $this->horaONulo($row['entrada_jornada_1'] ?? null),
                    'gabela_entrada_jornada_1' => $this->gabelaONulo($row['gabela_entrada_jornada_1'] ?? null),
                    'salida_jornada_1' => $this->horaONulo($row['salida_jornada_1'] ?? null),
                    'gabela_salida_jornada_1' => $this->gabelaONulo($row['gabela_salida_jornada_1'] ?? null),
                    'entrada_jornada_2' => $this->horaONulo($row['entrada_jornada_2'] ?? null),
                    'gabela_entrada_jornada_2' => $this->gabelaONulo($row['gabela_entrada_jornada_2'] ?? null),
                    'salida_jornada_2' => $this->horaONulo($row['salida_jornada_2'] ?? null),
                    'gabela_salida_jornada_2' => $this->gabelaONulo($row['gabela_salida_jornada_2'] ?? null),
                ]
            );
        }
    }

    private function gabelaONulo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return max(0, (int) $valor);
    }

    private function horaONulo(mixed $valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return $valor;
    }

    private function itemsVacios(): array
    {
        $items = [];

        foreach (AccesoHorarioItem::DIAS as $dia => $nombre) {
            $items[$dia] = [
                'dia' => $dia,
                'nombre' => $nombre,
                'entrada_jornada_1' => '',
                'gabela_entrada_jornada_1' => '',
                'salida_jornada_1' => '',
                'gabela_salida_jornada_1' => '',
                'entrada_jornada_2' => '',
                'gabela_entrada_jornada_2' => '',
                'salida_jornada_2' => '',
                'gabela_salida_jornada_2' => '',
            ];
        }

        return $items;
    }

    private function itemsDe(AccesoHorario $horario): array
    {
        $items = $this->itemsVacios();

        foreach ($horario->items as $item) {
            $items[$item->dia_semana] = [
                'dia' => $item->dia_semana,
                'nombre' => $item->nombreDia(),
                'entrada_jornada_1' => $item->hora('entrada_jornada_1') ?? '',
                'gabela_entrada_jornada_1' => $item->gabela('entrada_jornada_1'),
                'salida_jornada_1' => $item->hora('salida_jornada_1') ?? '',
                'gabela_salida_jornada_1' => $item->gabela('salida_jornada_1'),
                'entrada_jornada_2' => $item->hora('entrada_jornada_2') ?? '',
                'gabela_entrada_jornada_2' => $item->gabela('entrada_jornada_2'),
                'salida_jornada_2' => $item->hora('salida_jornada_2') ?? '',
                'gabela_salida_jornada_2' => $item->gabela('salida_jornada_2'),
            ];
        }

        return $items;
    }
}
