<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\Grupo;
use App\Models\Sede;
use App\Services\ExcelContactos;
use App\Services\ImportadorContactos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Padres de familia y catedráticos. El segmento {tipo} de la URL ("padres" o "catedraticos")
 * define con cuál se trabaja.
 */
class ContactoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:contactos.ver', only: ['index', 'exportar']),
            new Middleware('permission:contactos.crear', only: ['create', 'store']),
            new Middleware('permission:contactos.editar', only: ['edit', 'update', 'masivo']),
            new Middleware('permission:contactos.eliminar', only: ['destroy']),
            new Middleware('permission:contactos.importar', only: ['importarForm', 'importar', 'plantilla']),
        ];
    }

    public function index(Request $request, string $tipo): View
    {
        $config = $this->config($tipo);
        $contactos = $this->consultaFiltrada($request, $config['tipo'])
            ->with(['sede', 'grupos'])
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate(25)->withQueryString();

        return view('contactos.index', [
            'segmento' => $tipo,
            'config' => $config,
            'contactos' => $contactos,
            'sedes' => $this->sedesPermitidas($request),
            'grupos' => $this->gruposPermitidos($request, $config['tipo']),
            'totalSinCorreo' => $this->consultaBase($request, $config['tipo'])->whereNull('correo')->count(),
        ]);
    }

    public function create(Request $request, string $tipo): View
    {
        $config = $this->config($tipo);

        return view('contactos.create', [
            'segmento' => $tipo,
            'config' => $config,
            'contacto' => new Contacto(['tipo' => $config['tipo'], 'sede_id' => $request->user()->sedeRestringida()]),
            'sedes' => $this->sedesPermitidas($request),
            'grupos' => $this->gruposPermitidos($request, $config['tipo']),
        ]);
    }

    public function store(Request $request, string $tipo): RedirectResponse
    {
        $config = $this->config($tipo);
        [$datos, $grupos] = $this->validar($request, $config['tipo']);

        $contacto = Contacto::create($datos + ['tipo' => $config['tipo']]);
        $contacto->grupos()->sync($grupos);

        $siguiente = $request->boolean('crear_otro') ? route('contactos.create', $tipo) : route('contactos.index', $tipo);

        return redirect($siguiente)->with('success', "Se registró a {$contacto->nombre_completo}.");
    }

    public function edit(Request $request, string $tipo, Contacto $contacto): View
    {
        $config = $this->config($tipo);
        $this->autorizarContacto($request, $contacto, $config['tipo']);

        return view('contactos.edit', [
            'segmento' => $tipo,
            'config' => $config,
            'contacto' => $contacto->load('grupos'),
            'sedes' => $this->sedesPermitidas($request),
            'grupos' => $this->gruposPermitidos($request, $config['tipo']),
            // Historial de participación en reuniones y capacitaciones
            'historial' => $contacto->invitaciones()->with('evento')
                ->join('eventos', 'eventos.id', '=', 'invitaciones.evento_id')
                ->orderByDesc('eventos.inicio')->select('invitaciones.*')->limit(30)->get(),
        ]);
    }

    public function update(Request $request, string $tipo, Contacto $contacto): RedirectResponse
    {
        $config = $this->config($tipo);
        $this->autorizarContacto($request, $contacto, $config['tipo']);
        [$datos, $grupos] = $this->validar($request, $config['tipo'], $contacto);

        $contacto->update($datos);
        $contacto->grupos()->sync($grupos);

        return redirect()->route('contactos.index', $tipo)->with('success', "Se actualizaron los datos de {$contacto->nombre_completo}.");
    }

    public function destroy(Request $request, string $tipo, Contacto $contacto): RedirectResponse
    {
        $config = $this->config($tipo);
        $this->autorizarContacto($request, $contacto, $config['tipo']);

        $nombre = $contacto->nombre_completo;
        $contacto->delete();

        return redirect()->route('contactos.index', $tipo)->with('success', "Se eliminó a {$nombre}.");
    }

    /**
     * Acciones sobre varios contactos seleccionados: agregar o quitar de un grupo, eliminar.
     */
    public function masivo(Request $request, string $tipo): RedirectResponse
    {
        $config = $this->config($tipo);
        $datos = $request->validate([
            'accion' => ['required', Rule::in(['agregar_grupo', 'quitar_grupo', 'eliminar'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'grupo_id' => ['required_unless:accion,eliminar', 'nullable', 'integer'],
        ], [
            'ids.required' => 'Seleccione al menos un contacto.',
            'grupo_id.required_unless' => 'Seleccione el grupo.',
        ]);

        $contactos = $this->consultaBase($request, $config['tipo'])->whereIn('id', $datos['ids'])->get();

        if ($datos['accion'] === 'eliminar') {
            abort_unless($request->user()->can('contactos.eliminar'), 403);
            Contacto::whereIn('id', $contactos->pluck('id'))->delete();

            return back()->with('success', "Se eliminaron {$contactos->count()} contactos.");
        }

        $grupo = $this->gruposPermitidos($request, $config['tipo'])->firstWhere('id', (int) $datos['grupo_id']);
        abort_unless($grupo, 404);

        if ($datos['accion'] === 'quitar_grupo') {
            $grupo->contactos()->detach($contactos->pluck('id'));

            return back()->with('success', "Se quitaron {$contactos->count()} contactos del grupo {$grupo->nombre}.");
        }

        $admitidos = $contactos->filter(fn (Contacto $c) => $grupo->admite($c));
        $grupo->contactos()->syncWithoutDetaching($admitidos->pluck('id'));
        $rechazados = $contactos->count() - $admitidos->count();

        $mensaje = "Se agregaron {$admitidos->count()} contactos al grupo {$grupo->nombre}.";
        if ($rechazados) {
            $mensaje .= " {$rechazados} no se agregaron porque son de otra sede.";
        }

        return back()->with('success', $mensaje);
    }

    public function importarForm(Request $request, string $tipo): View
    {
        $config = $this->config($tipo);

        return view('contactos.importar', [
            'segmento' => $tipo,
            'config' => $config,
            'columnas' => ExcelContactos::columnas($config['tipo']),
            'grupos' => $this->gruposPermitidos($request, $config['tipo']),
            'sedes' => $this->sedesPermitidas($request),
            'resultado' => session('resultado'),
        ]);
    }

    public function importar(Request $request, string $tipo): RedirectResponse
    {
        $config = $this->config($tipo);
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'grupo_id' => ['nullable', 'integer'],
        ], [], ['archivo' => 'archivo de Excel']);

        $grupo = $request->filled('grupo_id')
            ? ($this->gruposPermitidos($request, $config['tipo'])->firstWhere('id', (int) $request->input('grupo_id')) ?? abort(404))
            : null;

        $importador = new ImportadorContactos(
            tipo: $config['tipo'],
            actualizarExistentes: $request->input('existentes', 'actualizar') === 'actualizar',
            sedeRestringida: $request->user()->sedeRestringida(),
            grupoDestino: $grupo,
        );

        try {
            $resultado = $importador->desdeArchivo($request->file('archivo')->getRealPath());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo leer el archivo. Verifique que sea un Excel (.xlsx) o CSV válido.');
        }

        return redirect()->route('contactos.importar', $tipo)->with('resultado', $resultado);
    }

    public function plantilla(string $tipo): StreamedResponse
    {
        return ExcelContactos::plantilla($this->config($tipo)['tipo']);
    }

    public function exportar(Request $request, string $tipo): StreamedResponse
    {
        $config = $this->config($tipo);
        $contactos = $this->consultaFiltrada($request, $config['tipo'])
            ->with(['sede', 'grupos'])->orderBy('apellidos')->orderBy('nombres')->get();

        return ExcelContactos::exportar($config['tipo'], $contactos);
    }

    // ----------------------------------------------------------------------

    private function config(string $segmento): array
    {
        return Contacto::TIPOS[$segmento] ?? abort(404);
    }

    /** Contactos del tipo, limitados a la sede del usuario si corresponde. */
    private function consultaBase(Request $request, string $tipo): Builder
    {
        return Contacto::tipo($tipo)
            ->when($request->user()->sedeRestringida(), fn ($q, $sede) => $q->where('sede_id', $sede));
    }

    private function consultaFiltrada(Request $request, string $tipo): Builder
    {
        return $this->consultaBase($request, $tipo)
            ->buscar($request->input('buscar'))
            ->when($request->filled('sede'), fn ($q) => $q->where('sede_id', $request->integer('sede')))
            ->when($request->filled('grupo'), fn ($q) => $q->whereHas('grupos', fn ($g) => $g->whereKey($request->integer('grupo'))))
            ->when($request->input('correo') === 'con', fn ($q) => $q->whereNotNull('correo'))
            ->when($request->input('correo') === 'sin', fn ($q) => $q->whereNull('correo'));
    }

    private function sedesPermitidas(Request $request)
    {
        return Sede::activas()
            ->when($request->user()->sedeRestringida(), fn ($q, $sede) => $q->whereKey($sede))
            ->orderBy('nombre')->get();
    }

    private function gruposPermitidos(Request $request, string $tipo)
    {
        return Grupo::compatibles($tipo, $request->user()->sedeRestringida())
            ->with('sede')->orderBy('nombre')->get();
    }

    private function autorizarContacto(Request $request, Contacto $contacto, string $tipo): void
    {
        abort_if($contacto->tipo !== $tipo, 404);
        $sede = $request->user()->sedeRestringida();
        abort_if($sede && $contacto->sede_id !== $sede, 403);
    }

    private function validar(Request $request, string $tipo, ?Contacto $contacto = null): array
    {
        $unico = fn (string $campo) => Rule::unique('contactos', $campo)->where('tipo', $tipo)->ignore($contacto);

        $reglas = [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'sede_id' => ['required', Rule::in($this->sedesPermitidas($request)->pluck('id'))],
            'dpi' => ['nullable', 'digits:13', $unico('dpi')],
            'correo' => ['nullable', 'email', 'max:150', $unico('correo')],
            'telefono' => ['nullable', 'regex:/^[0-9]{4}-?[0-9]{4}$/'],
            'acepta_correos' => ['boolean'],
            'grupos' => ['array'],
            'grupos.*' => ['integer'],
        ];
        if ($tipo === Contacto::PADRE) {
            $reglas += ['estudiante' => ['nullable', 'string', 'max:150'], 'grado_seccion' => ['nullable', 'string', 'max:60']];
        } else {
            $reglas += ['area' => ['nullable', 'string', 'max:100']];
        }

        $request->merge(['correo' => $request->filled('correo') ? mb_strtolower(trim($request->input('correo'))) : null]);

        $datos = $request->validate($reglas, [
            'telefono.regex' => 'El teléfono debe tener 8 dígitos (por ejemplo 5874-3210).',
            'dpi.unique' => 'Ya hay otro contacto registrado con este DPI.',
            'correo.unique' => 'Ya hay otro contacto registrado con este correo.',
            'sede_id.in' => 'Seleccione una sede válida.',
        ], ['sede_id' => 'sede', 'grado_seccion' => 'grado y sección', 'area' => 'curso o área', 'dpi' => 'DPI']);

        $datos['acepta_correos'] = $request->boolean('acepta_correos');
        if (! empty($datos['telefono']) && ! str_contains($datos['telefono'], '-')) {
            $datos['telefono'] = substr($datos['telefono'], 0, 4).'-'.substr($datos['telefono'], 4);
        }

        // Solo grupos compatibles con el tipo y la sede elegida
        $grupos = Grupo::compatibles($tipo, (int) $datos['sede_id'])
            ->whereIn('id', $datos['grupos'] ?? [])->pluck('id')->all();
        unset($datos['grupos']);

        return [$datos, $grupos];
    }
}
