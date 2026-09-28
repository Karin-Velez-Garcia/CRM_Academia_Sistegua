<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\PlantillaCertificado;
use App\Models\Sede;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Diseños de certificado: listado, editor visual y vista previa.
 */
class PlantillaCertificadoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:certificados.ver', only: ['index', 'vistaPrevia']),
            new Middleware('permission:certificados.crear', only: ['create', 'store', 'duplicar']),
            new Middleware('permission:certificados.editar', only: ['edit', 'update', 'predeterminada', 'imagen']),
            new Middleware('permission:certificados.eliminar', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        return view('certificados.index', [
            'plantillas' => PlantillaCertificado::withCount('eventos')->orderByDesc('predeterminada')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): RedirectResponse
    {
        $plantilla = PlantillaCertificado::create([
            'nombre' => 'Diseño nuevo',
            'orientacion' => PlantillaCertificado::HORIZONTAL,
            'elementos' => PlantillaCertificado::disenoInicial(),
            'predeterminada' => ! PlantillaCertificado::exists(),
        ]);

        return redirect()->route('certificados.edit', $plantilla)->with('success', 'Diseño creado. Acomode los elementos a su gusto.');
    }

    public function edit(PlantillaCertificado $certificado): View
    {
        return view('certificados.editor', [
            'plantilla' => $certificado,
            'imagenes' => $this->imagenesDisponibles(),
        ]);
    }

    public function update(Request $request, PlantillaCertificado $certificado): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'orientacion' => ['required', Rule::in([PlantillaCertificado::HORIZONTAL, PlantillaCertificado::VERTICAL])],
            'fondo' => ['nullable', 'string', 'max:255'],
            'elementos' => ['required', 'string'],
        ], [], ['nombre' => 'nombre del diseño']);

        $elementos = json_decode($datos['elementos'], true);
        abort_unless(is_array($elementos), 422, 'El diseño no se pudo leer.');

        $certificado->update([
            'nombre' => $datos['nombre'],
            'orientacion' => $datos['orientacion'],
            'fondo' => ! empty($datos['fondo']) ? basename($datos['fondo']) : null,
            'elementos' => PlantillaCertificado::sanear($elementos),
        ]);

        return back()->with('success', 'Diseño guardado.');
    }

    public function predeterminada(PlantillaCertificado $certificado): RedirectResponse
    {
        $certificado->marcarPredeterminada();

        return back()->with('success', "\"{$certificado->nombre}\" es ahora el diseño predeterminado.");
    }

    public function duplicar(PlantillaCertificado $certificado): RedirectResponse
    {
        $copia = PlantillaCertificado::create([
            'nombre' => mb_substr('Copia de '.$certificado->nombre, 0, 100),
            'orientacion' => $certificado->orientacion,
            'fondo' => $certificado->fondo,
            'elementos' => $certificado->elementos,
            'predeterminada' => false,
        ]);

        return redirect()->route('certificados.edit', $copia)->with('success', 'Se duplicó el diseño.');
    }

    public function destroy(PlantillaCertificado $certificado): RedirectResponse
    {
        if (PlantillaCertificado::count() === 1) {
            return back()->with('error', 'Debe quedar al menos un diseño de certificado.');
        }

        $certificado->delete();

        // Si se borró el predeterminado, el más antiguo que quede toma su lugar
        if (! PlantillaCertificado::where('predeterminada', true)->exists()) {
            PlantillaCertificado::orderBy('id')->first()?->marcarPredeterminada();
        }

        return redirect()->route('certificados.index')->with('success', 'Diseño eliminado.');
    }

    /** Sube una imagen (firma, sello, fondo) para usarla en los diseños. */
    public function imagen(Request $request): JsonResponse
    {
        $request->validate(['imagen' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:3072']]);

        $nombre = uniqid('img_').'.'.$request->file('imagen')->extension();
        $request->file('imagen')->storeAs('certificados', $nombre);

        return response()->json(['archivo' => $nombre, 'url' => route('certificados.imagen.ver', $nombre)]);
    }

    /** Sirve una imagen subida (el editor la necesita por URL). */
    public function verImagen(string $archivo): Response
    {
        $ruta = 'certificados/'.basename($archivo);
        abort_unless(Storage::exists($ruta), 404);

        return response(Storage::get($ruta), 200, ['Content-Type' => Storage::mimeType($ruta)]);
    }

    /** PDF de ejemplo con datos de muestra, para revisar el diseño antes de usarlo. */
    public function vistaPrevia(PlantillaCertificado $certificado): Response
    {
        $evento = Evento::with('sede')->latest('inicio')->first();

        if (! $evento) {
            $evento = new Evento([
                'titulo' => 'Instalación de tabla yeso', 'inicio' => now(), 'fin' => now()->addHours(4),
                'modalidad' => Evento::PRESENCIAL, 'lugar' => 'Centro de capacitación',
                'facilitador' => 'Ing. Mario Cabrera',
            ]);
            $evento->setRelation('sede', Sede::orderBy('nombre')->first() ?? new Sede(['nombre' => 'Sede principal']));
        }

        return Pdf::loadView('pdf.constancia', [
            'plantilla' => $certificado,
            'evento' => $evento,
            'invitaciones' => collect([null]),
        ])->setPaper('letter', $certificado->orientacion === PlantillaCertificado::VERTICAL ? 'portrait' : 'landscape')
            ->stream('vista-previa-'.str($certificado->nombre)->slug().'.pdf');
    }

    /** @return array<int, array{archivo: string, url: string}> */
    private function imagenesDisponibles(): array
    {
        return collect(Storage::files('certificados'))
            ->map(fn (string $ruta) => basename($ruta))
            ->map(fn (string $archivo) => ['archivo' => $archivo, 'url' => route('certificados.imagen.ver', $archivo)])
            ->values()->all();
    }
}
