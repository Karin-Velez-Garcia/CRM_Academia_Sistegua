<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Services\ExcelTabla;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Control de asistencia de un evento: quién llegó y quién no.
 */
class AsistenciaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:eventos.asistencia', only: ['show', 'guardar', 'agregar', 'buscar']),
            new Middleware('permission:eventos.ver', only: ['pdf', 'excel']),
        ];
    }

    public function show(Request $request, string $tipo, Evento $evento): View
    {
        $config = $this->autorizar($request, $tipo, $evento);
        $lista = $this->lista($evento);

        $filtro = $request->input('filtro');
        $buscar = mb_strtolower(trim((string) $request->input('buscar')));
        $visibles = $lista->filter(function (array $fila) use ($filtro, $buscar) {
            $inv = $fila['invitacion'];
            $pasa = match ($filtro) {
                'confirmados' => $inv?->respuesta === Invitacion::CONFIRMADA,
                'presentes' => $inv?->asistio === true,
                'ausentes' => $inv?->asistio === false,
                'sin_marcar' => $inv?->asistio === null,
                default => true,
            };
            if ($pasa && $buscar !== '') {
                $c = $fila['contacto'];
                $pasa = str_contains(mb_strtolower($c->nombre_completo.' '.$c->estudiante.' '.$c->grado_seccion.' '.$c->area), $buscar);
            }

            return $pasa;
        });

        return view('asistencia.show', [
            'segmento' => $tipo,
            'config' => $config,
            'evento' => $evento->load('sede'),
            'lista' => $visibles,
            'resumen' => $this->resumen($lista),
            'filtro' => $filtro,
            'puedeMarcar' => $this->puedeMarcar($evento),
        ]);
    }

    /** Guarda de una vez la asistencia de todas las personas visibles en la lista. */
    public function guardar(Request $request, string $tipo, Evento $evento): RedirectResponse
    {
        $this->autorizar($request, $tipo, $evento);
        abort_unless($this->puedeMarcar($evento), 422, 'Todavía no se puede tomar asistencia de este evento.');

        $datos = $request->validate([
            'contactos' => ['required', 'array'],
            'contactos.*' => ['integer'],
            'presentes' => ['array'],
            'presentes.*' => ['integer'],
        ]);

        $permitidos = $this->lista($evento)->keys();
        $contactos = collect($datos['contactos'])->map(fn ($id) => (int) $id)->intersect($permitidos);
        $presentes = collect($datos['presentes'] ?? [])->map(fn ($id) => (int) $id);

        foreach ($contactos as $contactoId) {
            $this->marcar($evento, Contacto::find($contactoId), $presentes->contains($contactoId), $request->user()->id);
        }

        $total = $evento->invitaciones()->asistentes()->count();

        return back()->with('success', "Asistencia guardada: {$total} ".($total === 1 ? 'persona presente' : 'personas presentes').'.');
    }

    /** Registra a alguien que llegó sin estar en la lista (sin invitación). */
    public function agregar(Request $request, string $tipo, Evento $evento): RedirectResponse
    {
        $this->autorizar($request, $tipo, $evento);
        abort_unless($this->puedeMarcar($evento), 422, 'Todavía no se puede tomar asistencia de este evento.');
        $datos = $request->validate([
            'contacto_id' => ['required', Rule::exists('contactos', 'id')->where('sede_id', $evento->sede_id)],
        ], ['contacto_id.exists' => 'Seleccione una persona registrada en la sede del evento.']);

        $contacto = Contacto::findOrFail($datos['contacto_id']);
        $this->marcar($evento, $contacto, true, $request->user()->id);

        return back()->with('success', "Se registró la asistencia de {$contacto->nombre_completo}.");
    }

    /** Búsqueda de personas de la sede para agregarlas a la lista. */
    public function buscar(Request $request, string $tipo, Evento $evento): JsonResponse
    {
        $this->autorizar($request, $tipo, $evento);
        $enLista = $this->lista($evento)->keys();

        $resultados = Contacto::where('sede_id', $evento->sede_id)
            ->buscar($request->input('q'))
            ->whereNotIn('id', $enLista)
            ->orderBy('apellidos')->limit(10)->get()
            ->map(fn (Contacto $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre_completo,
                'detalle' => $c->tipo === Contacto::PADRE
                    ? trim('Padre/madre · '.$c->estudiante.' '.$c->grado_seccion)
                    : trim('Catedrático · '.$c->area),
            ]);

        return response()->json($resultados);
    }

    /** Lista impresa para firmas (útil para reuniones presenciales). */
    public function pdf(Request $request, string $tipo, Evento $evento): Response
    {
        $config = $this->autorizar($request, $tipo, $evento);
        $lista = $this->lista($evento->load('sede.municipio.departamento'));

        return Pdf::loadView('pdf.lista_asistencia', [
            'evento' => $evento, 'config' => $config, 'lista' => $lista, 'resumen' => $this->resumen($lista),
        ])->setPaper('letter')->download('asistencia-'.str($evento->titulo)->slug().'.pdf');
    }

    public function excel(Request $request, string $tipo, Evento $evento): StreamedResponse
    {
        $config = $this->autorizar($request, $tipo, $evento);
        $esPadre = $config['publico'] === Contacto::PADRE;

        $filas = $this->lista($evento)->values()->map(function (array $f, int $i) use ($esPadre) {
            $c = $f['contacto'];
            $inv = $f['invitacion'];

            return array_merge(
                [$i + 1, $c->nombre_completo],
                $esPadre ? [$c->estudiante, $c->grado_seccion] : [$c->area],
                [
                    $c->correo ?: 'Sin correo',
                    match ($inv?->estado_envio) {
                        Invitacion::ENVIADA => 'Enviada',
                        Invitacion::FALLIDA => 'Error de envío',
                        Invitacion::PENDIENTE => 'Pendiente',
                        default => 'No se envió',
                    },
                    match ($inv?->respuesta) {
                        Invitacion::CONFIRMADA => 'Confirmó',
                        Invitacion::RECHAZADA => 'No asistirá',
                        default => 'Sin respuesta',
                    },
                    match ($inv?->asistio) {
                        true => 'Presente',
                        false => 'Ausente',
                        default => 'Sin marcar',
                    },
                    $inv?->comentario,
                ]
            );
        });

        $encabezados = array_merge(
            ['#', $esPadre ? 'Padre o madre' : 'Catedrático'],
            $esPadre ? ['Estudiante', 'Grado y sección'] : ['Curso o área'],
            ['Correo', 'Invitación', 'Respuesta', 'Asistencia', 'Comentario']
        );

        return ExcelTabla::descargar(
            'Asistencia',
            $encabezados,
            $filas,
            'asistencia-'.str($evento->titulo)->slug().'.xlsx',
            "{$evento->titulo} · {$evento->horario} · Sede {$evento->sede->nombre}",
        );
    }

    // ----------------------------------------------------------------------

    /**
     * Personas de la lista (clave = id del contacto): destinatarios del evento
     * más cualquiera que ya tenga registro (invitados antes o agregados al llegar).
     *
     * @return Collection<int, array{contacto: Contacto, invitacion: ?Invitacion}>
     */
    private function lista(Evento $evento): Collection
    {
        $invitaciones = $evento->invitaciones()->with('contacto')->get()->keyBy('contacto_id');
        $destinatarios = $evento->destinatarios()->get()->keyBy('id');

        $contactos = $destinatarios->union($invitaciones->mapWithKeys(fn ($i) => [$i->contacto_id => $i->contacto]));

        return $contactos
            ->sortBy(fn (Contacto $c) => mb_strtolower($c->apellidos.' '.$c->nombres))
            ->map(fn (Contacto $c) => ['contacto' => $c, 'invitacion' => $invitaciones->get($c->id)]);
    }

    private function resumen(Collection $lista): array
    {
        $inv = $lista->pluck('invitacion')->filter();

        return [
            'total' => $lista->count(),
            'confirmados' => $inv->where('respuesta', Invitacion::CONFIRMADA)->count(),
            // whereStrict: "sin marcar" (null) no debe contarse como ausente (false)
            'presentes' => $inv->whereStrict('asistio', true)->count(),
            'ausentes' => $inv->whereStrict('asistio', false)->count(),
            'sin_marcar' => $lista->count() - $inv->whereNotNull('asistio')->count(),
            'confirmados_presentes' => $inv->where('respuesta', Invitacion::CONFIRMADA)->whereStrict('asistio', true)->count(),
            'sin_confirmar_presentes' => $inv->whereStrict('asistio', true)->where('respuesta', '!=', Invitacion::CONFIRMADA)->count(),
        ];
    }

    private function marcar(Evento $evento, Contacto $contacto, bool $presente, int $usuarioId): void
    {
        Invitacion::registrarAsistencia($evento, $contacto, $presente, $usuarioId, 'manual');
    }

    /** Se toma asistencia desde el día del evento (no antes) y nunca en eventos cancelados. */
    private function puedeMarcar(Evento $evento): bool
    {
        return $evento->cancelado_at === null && $evento->inicio->copy()->startOfDay()->lte(now());
    }

    private function autorizar(Request $request, string $tipo, Evento $evento): array
    {
        $config = Evento::TIPOS[$tipo] ?? abort(404);
        abort_if($config['tipo'] !== $evento->tipo, 404);
        $sede = $request->user()->sedeRestringida();
        abort_if($sede && $evento->sede_id !== $sede, 403);

        return $config;
    }
}
