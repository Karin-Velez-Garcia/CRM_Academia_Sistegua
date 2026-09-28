<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Sede;
use App\Services\ExcelTabla;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes de participación: por evento y por persona.
 */
class ReporteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:reportes.ver', only: ['eventos', 'personas']),
            new Middleware('permission:reportes.exportar', only: ['eventosExcel', 'personasExcel']),
        ];
    }

    public function eventos(Request $request): View
    {
        [$desde, $hasta] = $this->rango($request);
        $eventos = $this->consultaEventos($request, $desde, $hasta)->get();

        $porSede = $eventos->groupBy(fn ($e) => $e->sede->nombre)->map(fn ($grupo) => [
            'eventos' => $grupo->count(),
            'invitados' => $grupo->sum('invitados_count'),
            'confirmados' => $grupo->sum('confirmados_count'),
            'asistentes' => $grupo->sum('asistentes_count'),
        ])->sortKeys();

        return view('reportes.eventos', [
            'eventos' => $eventos,
            'porSede' => $porSede,
            'totales' => [
                'eventos' => $eventos->count(),
                'invitados' => $eventos->sum('invitados_count'),
                'confirmados' => $eventos->sum('confirmados_count'),
                'asistentes' => $eventos->sum('asistentes_count'),
            ],
            'desde' => $desde, 'hasta' => $hasta,
            'sedes' => $this->sedes($request),
        ]);
    }

    public function eventosExcel(Request $request): StreamedResponse
    {
        [$desde, $hasta] = $this->rango($request);
        $filas = $this->consultaEventos($request, $desde, $hasta)->get()->map(fn (Evento $e) => [
            $e->inicio->format('d/m/Y'),
            $e->tipo === Evento::REUNION ? 'Reunión' : 'Capacitación',
            $e->titulo,
            $e->sede->nombre,
            $e->es_virtual ? 'Virtual' : 'Presencial',
            $e->estado_etiqueta[0],
            (int) $e->invitados_count,
            (int) $e->confirmados_count,
            (int) $e->invitados_count - (int) $e->confirmados_count,
            (int) $e->rechazados_count,
            (int) $e->asistentes_count,
            (int) $e->invitados_count - (int) $e->asistentes_invitados_count,
            self::porcentaje($e->asistentes_count, $e->invitados_count),
        ]);

        return ExcelTabla::descargar(
            'Reporte por evento',
            ['Fecha', 'Tipo', 'Evento', 'Sede', 'Modalidad', 'Estado', 'Invitaciones enviadas', 'Confirmadas', 'No confirmadas', 'Dijeron que no', 'Asistencias', 'Inasistencias', '% asistencia'],
            $filas,
            'reporte-eventos-'.$desde->format('Ymd').'-'.$hasta->format('Ymd').'.xlsx',
            config('colegio.nombre').' · Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y'),
        );
    }

    public function personas(Request $request): View
    {
        [$desde, $hasta] = $this->rango($request);
        $personas = $this->consultaPersonas($request, $desde, $hasta)->paginate(25)->withQueryString();

        return view('reportes.personas', [
            'personas' => $personas,
            'desde' => $desde, 'hasta' => $hasta,
            'sedes' => $this->sedes($request),
        ]);
    }

    public function personasExcel(Request $request): StreamedResponse
    {
        [$desde, $hasta] = $this->rango($request);
        $filas = $this->consultaPersonas($request, $desde, $hasta)->get()->map(fn (Contacto $c) => [
            $c->nombre_completo,
            $c->tipo === Contacto::PADRE ? 'Padre de familia' : 'Catedrático',
            $c->sede->nombre,
            $c->tipo === Contacto::PADRE ? trim($c->estudiante.' '.$c->grado_seccion) : (string) $c->area,
            (string) $c->correo,
            (int) $c->convocatorias_count,
            (int) $c->confirmadas_count,
            (int) $c->asistencias_count,
            (int) $c->ausencias_count,
            self::porcentaje($c->asistencias_count, $c->convocatorias_count),
        ]);

        return ExcelTabla::descargar(
            'Reporte por persona',
            ['Nombre', 'Tipo', 'Sede', 'Estudiante / curso', 'Correo', 'Convocatorias', 'Confirmó', 'Asistió', 'Faltó', '% asistencia'],
            $filas,
            'reporte-personas-'.$desde->format('Ymd').'-'.$hasta->format('Ymd').'.xlsx',
            config('colegio.nombre').' · Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y'),
        );
    }

    public static function porcentaje(int|float|null $parte, int|float|null $total): string
    {
        return $total ? round($parte / $total * 100).'%' : '—';
    }

    // ----------------------------------------------------------------------

    /** Por defecto, el año en curso. */
    private function rango(Request $request): array
    {
        $request->validate(['desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date']]);
        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : now()->startOfYear();
        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : now()->endOfYear();

        return $desde->gt($hasta) ? [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()] : [$desde, $hasta];
    }

    private function sedeFiltro(Request $request): ?int
    {
        return $request->user()->sedeRestringida() ?? ($request->filled('sede') ? $request->integer('sede') : null);
    }

    private function sedes(Request $request)
    {
        return Sede::when($request->user()->sedeRestringida(), fn ($q, $s) => $q->whereKey($s))->orderBy('nombre')->get();
    }

    private function consultaEventos(Request $request, Carbon $desde, Carbon $hasta): Builder
    {
        $invitadas = fn ($q) => $q->where('estado_envio', '!=', Invitacion::NO_ENVIADA);

        return Evento::with('sede')
            ->whereBetween('inicio', [$desde, $hasta])
            ->when($this->sedeFiltro($request), fn ($q, $s) => $q->where('sede_id', $s))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->when(! $request->boolean('cancelados'), fn ($q) => $q->whereNull('cancelado_at'))
            ->withCount([
                'invitaciones as invitados_count' => $invitadas,
                'invitaciones as confirmados_count' => fn ($q) => $q->where('respuesta', Invitacion::CONFIRMADA),
                'invitaciones as rechazados_count' => fn ($q) => $q->where('respuesta', Invitacion::RECHAZADA),
                'invitaciones as asistentes_count' => fn ($q) => $q->where('asistio', true),
                'invitaciones as asistentes_invitados_count' => fn ($q) => $q->where('estado_envio', Invitacion::ENVIADA)->where('asistio', true),
            ])
            ->orderBy('inicio');
    }

    /** Personas con al menos una convocatoria en el período, ordenadas de menor a mayor asistencia. */
    private function consultaPersonas(Request $request, Carbon $desde, Carbon $hasta): Builder
    {
        $delPeriodo = fn ($q) => $q->whereHas('evento', fn ($e) => $e->whereBetween('inicio', [$desde, $hasta])
            ->whereNull('cancelado_at')->where('fin', '<', now()));

        return Contacto::with('sede')
            ->when($this->sedeFiltro($request), fn ($q, $s) => $q->where('sede_id', $s))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->buscar($request->input('buscar'))
            ->whereHas('invitaciones', $delPeriodo)
            ->withCount([
                'invitaciones as convocatorias_count' => $delPeriodo,
                'invitaciones as confirmadas_count' => fn ($q) => $delPeriodo($q)->where('respuesta', Invitacion::CONFIRMADA),
                'invitaciones as asistencias_count' => fn ($q) => $delPeriodo($q)->where('asistio', true),
                'invitaciones as ausencias_count' => fn ($q) => $delPeriodo($q)->where('asistio', false),
            ])
            ->orderByRaw('asistencias_count / NULLIF(convocatorias_count, 0) asc')
            ->orderBy('apellidos');
    }
}
