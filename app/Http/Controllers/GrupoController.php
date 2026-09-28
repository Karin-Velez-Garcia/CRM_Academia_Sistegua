<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\Grupo;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Grupos de contactos (por ejemplo "Padres de 3.º Básico" o "Claustro de primaria"),
 * que después se usan para enviar invitaciones.
 */
class GrupoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:contactos.ver', only: ['index', 'show']),
            new Middleware('permission:contactos.editar', only: ['create', 'store', 'edit', 'update', 'destroy', 'quitar']),
        ];
    }

    public function index(Request $request): View
    {
        $sede = $request->user()->sedeRestringida();

        $grupos = Grupo::with('sede')
            ->withCount('contactos')
            ->when($sede, fn ($q) => $q->where(fn ($w) => $w->whereNull('sede_id')->orWhere('sede_id', $sede)))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->when($request->filled('sede'), fn ($q) => $q->where('sede_id', $request->integer('sede')))
            ->when($request->filled('buscar'), fn ($q) => $q->where('nombre', 'like', '%'.$request->input('buscar').'%'))
            ->orderBy('nombre')
            ->paginate(20)->withQueryString();

        return view('grupos.index', ['grupos' => $grupos, 'sedes' => $this->sedes($request)]);
    }

    public function create(Request $request): View
    {
        return view('grupos.create', [
            'grupo' => new Grupo(['tipo' => $request->input('tipo', Contacto::PADRE), 'sede_id' => $request->user()->sedeRestringida()]),
            'sedes' => $this->sedes($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $grupo = Grupo::create($this->validar($request));

        return redirect()->route('grupos.show', $grupo)
            ->with('success', "Se creó el grupo {$grupo->nombre}. Agregue miembros desde las listas de padres de familia o catedráticos.");
    }

    public function show(Request $request, Grupo $grupo): View
    {
        $this->autorizar($request, $grupo);
        $sede = $request->user()->sedeRestringida();

        $miembros = $grupo->contactos()->with('sede')
            ->when($sede, fn ($q) => $q->where('sede_id', $sede))
            ->buscar($request->input('buscar'))
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate(25)->withQueryString();

        return view('grupos.show', [
            'grupo' => $grupo->load('sede'),
            'miembros' => $miembros,
            'conCorreo' => $grupo->contactos()->whereNotNull('correo')->where('acepta_correos', true)->count(),
        ]);
    }

    public function edit(Request $request, Grupo $grupo): View
    {
        $this->autorizar($request, $grupo, editar: true);

        return view('grupos.edit', ['grupo' => $grupo, 'sedes' => $this->sedes($request)]);
    }

    public function update(Request $request, Grupo $grupo): RedirectResponse
    {
        $this->autorizar($request, $grupo, editar: true);
        $datos = $this->validar($request, $grupo);

        $grupo->update($datos);

        // Si el grupo cambió de tipo o de sede, se quitan los miembros que ya no corresponden
        $fuera = $grupo->contactos()->get()->reject(fn (Contacto $c) => $grupo->admite($c));
        if ($fuera->isNotEmpty()) {
            $grupo->contactos()->detach($fuera->pluck('id'));
        }

        $mensaje = "Se actualizó el grupo {$grupo->nombre}.";
        if ($fuera->isNotEmpty()) {
            $mensaje .= " Se quitaron {$fuera->count()} miembros que ya no corresponden al tipo o la sede del grupo.";
        }

        return redirect()->route('grupos.show', $grupo)->with('success', $mensaje);
    }

    public function destroy(Request $request, Grupo $grupo): RedirectResponse
    {
        $this->autorizar($request, $grupo, editar: true);
        $nombre = $grupo->nombre;
        $grupo->delete(); // los contactos no se eliminan, solo la pertenencia

        return redirect()->route('grupos.index')->with('success', "Se eliminó el grupo {$nombre}. Los contactos siguen registrados.");
    }

    public function quitar(Request $request, Grupo $grupo, Contacto $contacto): RedirectResponse
    {
        $this->autorizar($request, $grupo);
        $sede = $request->user()->sedeRestringida();
        abort_if($sede && $contacto->sede_id !== $sede, 403);

        $grupo->contactos()->detach($contacto->id);

        return back()->with('success', "Se quitó a {$contacto->nombre_completo} del grupo.");
    }

    // ----------------------------------------------------------------------

    private function sedes(Request $request)
    {
        return Sede::activas()
            ->when($request->user()->sedeRestringida(), fn ($q, $s) => $q->whereKey($s))
            ->orderBy('nombre')->get();
    }

    /** Un usuario limitado a una sede ve los grupos de su sede y los generales, pero solo edita los de su sede. */
    private function autorizar(Request $request, Grupo $grupo, bool $editar = false): void
    {
        $sede = $request->user()->sedeRestringida();
        if (! $sede) {
            return;
        }
        abort_if($grupo->sede_id !== null && $grupo->sede_id !== $sede, 403);
        abort_if($editar && $grupo->sede_id === null, 403, 'Solo un administrador puede modificar los grupos de todas las sedes.');
    }

    private function validar(Request $request, ?Grupo $grupo = null): array
    {
        $sedeUsuario = $request->user()->sedeRestringida();
        if ($sedeUsuario) {
            $request->merge(['sede_id' => $sedeUsuario]);
        }

        return $request->validate([
            'nombre' => ['required', 'string', 'max:100',
                Rule::unique('grupos')->where(fn ($q) => $request->filled('sede_id')
                    ? $q->where('sede_id', $request->input('sede_id'))
                    : $q->whereNull('sede_id'))->ignore($grupo)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(array_keys(Grupo::TIPOS))],
            'sede_id' => ['nullable', Rule::exists('sedes', 'id')],
        ], [
            'nombre.unique' => 'Ya existe un grupo con ese nombre en la misma sede.',
        ], ['sede_id' => 'sede']);
    }
}
