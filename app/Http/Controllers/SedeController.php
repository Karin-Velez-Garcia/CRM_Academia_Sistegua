<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\Departamento;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SedeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:sedes.ver', only: ['index']),
            new Middleware('permission:sedes.crear', only: ['create', 'store']),
            new Middleware('permission:sedes.editar', only: ['edit', 'update']),
            new Middleware('permission:sedes.eliminar', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $sedes = Sede::with('municipio.departamento')
            ->withCount([
                'contactos as padres_count' => fn ($q) => $q->where('tipo', Contacto::PADRE),
                'contactos as catedraticos_count' => fn ($q) => $q->where('tipo', Contacto::CATEDRATICO),
                'usuarios',
            ])
            ->orderBy('nombre')->get();

        return view('sedes.index', ['sedes' => $sedes]);
    }

    public function create(): View
    {
        return view('sedes.create', ['sede' => new Sede(), 'departamentos' => Departamento::orderBy('nombre')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sede = Sede::create($this->validar($request));

        return redirect()->route('sedes.index')->with('success', "Se creó la sede {$sede->nombre}.");
    }

    public function edit(Sede $sede): View
    {
        return view('sedes.edit', [
            'sede' => $sede->load('municipio.departamento'),
            'departamentos' => Departamento::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Sede $sede): RedirectResponse
    {
        $sede->update($this->validar($request, $sede));

        return redirect()->route('sedes.index')->with('success', "Se actualizó la sede {$sede->nombre}.");
    }

    public function destroy(Sede $sede): RedirectResponse
    {
        if ($sede->contactos()->exists() || $sede->usuarios()->exists()) {
            return back()->with('error', "No se puede eliminar la sede {$sede->nombre} porque tiene contactos o usuarios. Puede desactivarla.");
        }

        $nombre = $sede->nombre;
        $sede->delete();

        return redirect()->route('sedes.index')->with('success', "Se eliminó la sede {$nombre}.");
    }

    private function validar(Request $request, ?Sede $sede = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80', Rule::unique('sedes')->ignore($sede)],
            'municipio_id' => ['required', Rule::exists('municipios', 'id')],
            'direccion' => ['required', 'string', 'max:200'],
            'telefono' => ['nullable', 'regex:/^[0-9]{4}-?[0-9]{4}$/'],
            'correo' => ['nullable', 'email', 'max:150'],
            'activa' => ['boolean'],
        ], [
            'telefono.regex' => 'El teléfono debe tener 8 dígitos (por ejemplo 7945-1234).',
        ], ['municipio_id' => 'municipio']);
        $datos['activa'] = $request->boolean('activa');

        return $datos;
    }
}
