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
            new Middleware('permission:campanias.desactivar', only: ['estado']),
        ];
    }

    public function index(): View
    {
        return view('plantillas.index', ['plantillas' => Plantilla::orderBy('tipo_evento')->orderByDesc('activa')->orderByDesc('predeterminada')->orderBy('nombre')->get()]);
    }

    public function create(Request $request): View
    {
        return view('plantillas.create', ['plantilla' => new Plantilla(['tipo_evento' => $request->input('tipo', Evento::CAPACITACION)])]);
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

    /** Las plantillas no se eliminan: las inactivas ya no se ofrecen al enviar invitaciones. */
    public function estado(Plantilla $plantilla): RedirectResponse
    {
        if ($plantilla->activa && $plantilla->predeterminada) {
            return back()->with('error', "No se puede desactivar la plantilla predeterminada. Marque otra como predeterminada primero.");
        }

        $plantilla->update(['activa' => ! $plantilla->activa]);

        return back()->with('success', $plantilla->activa
            ? "Se activó la plantilla {$plantilla->nombre}."
            : "Se desactivó la plantilla {$plantilla->nombre}.");
    }

    private function guardar(Plantilla $plantilla, Request $request): Plantilla
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'tipo_evento' => ['required', Rule::in([Evento::CAPACITACION])],
            'asunto' => ['required', 'string', 'max:200'],
            'mensaje' => ['required', 'string', 'max:5000'],
            'predeterminada' => ['boolean'],
        ], [], ['tipo_evento' => 'tipo de evento']);
        $datos['predeterminada'] = $request->boolean('predeterminada');

        // Solo una predeterminada por tipo de evento, y siempre activa
        if ($datos['predeterminada']) {
            $datos['activa'] = true;
            Plantilla::where('tipo_evento', $datos['tipo_evento'])->whereKeyNot($plantilla->id)->update(['predeterminada' => false]);
        }

        $plantilla->fill($datos)->save();

        return $plantilla;
    }
}
