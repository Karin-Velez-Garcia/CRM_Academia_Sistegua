<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Plantilla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlantillaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:campanias.ver', only: ['index']),
            new Middleware('permission:campanias.crear', only: ['create', 'store']),
            new Middleware('permission:campanias.editar', only: ['edit', 'update']),
            new Middleware('permission:campanias.eliminar', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        return view('plantillas.index', ['plantillas' => Plantilla::orderBy('tipo_evento')->orderByDesc('predeterminada')->orderBy('nombre')->get()]);
    }

    public function create(Request $request): View
    {
        return view('plantillas.create', ['plantilla' => new Plantilla(['tipo_evento' => $request->input('tipo', Evento::REUNION)])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plantilla = DB::transaction(fn () => $this->guardar(new Plantilla(), $request));

        return redirect()->route('plantillas.index')->with('success', "Se creó la plantilla {$plantilla->nombre}.");
    }

    public function edit(Plantilla $plantilla): View
    {
        return view('plantillas.edit', ['plantilla' => $plantilla]);
    }

    public function update(Request $request, Plantilla $plantilla): RedirectResponse
    {
        DB::transaction(fn () => $this->guardar($plantilla, $request));

        return redirect()->route('plantillas.index')->with('success', "Se actualizó la plantilla {$plantilla->nombre}.");
    }

    public function destroy(Plantilla $plantilla): RedirectResponse
    {
        if (Plantilla::where('tipo_evento', $plantilla->tipo_evento)->count() === 1) {
            return back()->with('error', 'Debe quedar al menos una plantilla para cada tipo de evento.');
        }

        $nombre = $plantilla->nombre;
        $plantilla->delete();

        return redirect()->route('plantillas.index')->with('success', "Se eliminó la plantilla {$nombre}.");
    }

    private function guardar(Plantilla $plantilla, Request $request): Plantilla
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'tipo_evento' => ['required', Rule::in([Evento::REUNION, Evento::CAPACITACION])],
            'asunto' => ['required', 'string', 'max:200'],
            'mensaje' => ['required', 'string', 'max:5000'],
            'predeterminada' => ['boolean'],
        ], [], ['tipo_evento' => 'tipo de evento']);
        $datos['predeterminada'] = $request->boolean('predeterminada');

        // Solo una predeterminada por tipo de evento
        if ($datos['predeterminada']) {
            Plantilla::where('tipo_evento', $datos['tipo_evento'])->whereKeyNot($plantilla->id)->update(['predeterminada' => false]);
        }

        $plantilla->fill($datos)->save();

        return $plantilla;
    }
}
