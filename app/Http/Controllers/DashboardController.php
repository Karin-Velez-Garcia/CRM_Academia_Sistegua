<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Tablero de inicio: indicadores de invitaciones, confirmaciones y asistencia,
 * próximos eventos y avisos de lo que requiere atención.
 */
class DashboardController extends Controller
{
    public const PERIODOS = [
        '30' => 'Últimos 30 días',
        '90' => 'Últimos 3 meses',
        '180' => 'Últimos 6 meses',
        '365' => 'Último año',
    ];

    public function __invoke(Request $request): View
    {
        $restringida = $request->user()->sedeRestringida();
        $sedeId = $restringida ?? ($request->filled('sede') ? $request->integer('sede') : null);
        $periodo = array_key_exists($request->input('periodo'), self::PERIODOS) ? $request->input('periodo') : '180';
        $desde = now()->subDays((int) $periodo)->startOfDay();
        $porSede = fn ($q) => $q->when($sedeId, fn ($w) => $w->where('sede_id', $sedeId));

        // Eventos ya realizados del período (con sus conteos de invitación y asistencia)
        $realizados = Evento::with('sede')->whereNull('cancelado_at')
            ->whereBetween('inicio', [$desde, now()])->where('fin', '<', now())
            ->tap($porSede)
            ->withCount($this->conteos())
            ->orderBy('inicio')->get();

        $totales = [
            'enviadas' => (int) $realizados->sum('enviadas_count'),
            'confirmadas' => (int) $realizados->sum('confirmadas_count'),
            'rechazadas' => (int) $realizados->sum('rechazadas_count'),
            'asistencias' => (int) $realizados->sum('asistencias_count'),
            'asistencias_invitados' => (int) $realizados->sum('asistencias_invitados_count'),
        ];
        $totales['sin_respuesta'] = max(0, $totales['enviadas'] - $totales['confirmadas'] - $totales['rechazadas']);

        $proximos = $request->user()->can('eventos.ver')
            ? Evento::with('sede')->proximos()->tap($porSede)->withCount($this->conteos())->orderBy('inicio')->limit(6)->get()
            : collect();

        return view('dashboard', [
            'periodo' => $periodo,
            'sedeId' => $sedeId,
            'sedes' => $restringida ? collect() : Sede::activas()->orderBy('nombre')->get(),
            'kpi' => [
                'padres' => Contacto::tipo(Contacto::PADRE)->tap($porSede)->count(),
                'catedraticos' => Contacto::tipo(Contacto::CATEDRATICO)->tap($porSede)->count(),
                'proximos_30' => Evento::proximos()->tap($porSede)->where('inicio', '<=', now()->addDays(30))->count(),
                'realizados' => $realizados->count(),
                'tasa_confirmacion' => self::tasa($totales['confirmadas'], $totales['enviadas']),
                'tasa_asistencia' => self::tasa($totales['asistencias_invitados'], $totales['enviadas']),
            ],
            'totales' => $totales,
            'porMes' => $this->porMes($realizados, $desde),
            'porSede' => $this->porSede($realizados),
            'proximos' => $proximos,
            'recientes' => $realizados->sortByDesc('inicio')->take(6)->values(),
            'avisos' => $this->avisos($request, $porSede),
        ]);
    }

    public static function tasa(int $parte, int $total): ?int
    {
        return $total > 0 ? (int) round($parte / $total * 100) : null;
    }

    private function conteos(): array
    {
        return [
            'invitaciones as enviadas_count' => fn ($q) => $q->where('estado_envio', Invitacion::ENVIADA),
            'invitaciones as confirmadas_count' => fn ($q) => $q->where('estado_envio', Invitacion::ENVIADA)->where('respuesta', Invitacion::CONFIRMADA),
            'invitaciones as rechazadas_count' => fn ($q) => $q->where('estado_envio', Invitacion::ENVIADA)->where('respuesta', Invitacion::RECHAZADA),
            'invitaciones as asistencias_count' => fn ($q) => $q->where('asistio', true),
            'invitaciones as asistencias_invitados_count' => fn ($q) => $q->where('estado_envio', Invitacion::ENVIADA)->where('asistio', true),
        ];
    }

    /** Series por mes para la gráfica: enviadas, confirmadas y asistencias. */
    private function porMes(Collection $eventos, Carbon $desde): array
    {
        $meses = [];
        for ($m = $desde->copy()->startOfMonth(); $m->lte(now()); $m->addMonth()) {
            $meses[$m->format('Y-m')] = ['etiqueta' => ucfirst($m->translatedFormat('M Y')), 'enviadas' => 0, 'confirmadas' => 0, 'asistencias' => 0, 'eventos' => 0];
        }
        foreach ($eventos as $e) {
            $k = $e->inicio->format('Y-m');
            if (isset($meses[$k])) {
                $meses[$k]['enviadas'] += $e->enviadas_count;
                $meses[$k]['confirmadas'] += $e->confirmadas_count;
                $meses[$k]['asistencias'] += $e->asistencias_count;
                $meses[$k]['eventos']++;
            }
        }

        return array_values($meses);
    }

    private function porSede(Collection $eventos): array
    {
        return $eventos->groupBy(fn ($e) => $e->sede->nombre)->map(fn ($g, $nombre) => [
            'sede' => $nombre,
            'eventos' => $g->count(),
            'enviadas' => (int) $g->sum('enviadas_count'),
            'asistencias' => (int) $g->sum('asistencias_count'),
            'tasa' => self::tasa((int) $g->sum('asistencias_invitados_count'), (int) $g->sum('enviadas_count')),
        ])->sortBy('sede')->values()->all();
    }

    /** Lo que requiere atención, con enlace directo. */
    private function avisos(Request $request, \Closure $porSede): array
    {
        $u = $request->user();
        $avisos = [];

        if ($u->can('eventos.ver')) {
            $hoy = Evento::whereNull('cancelado_at')->tap($porSede)
                ->whereBetween('inicio', [now()->startOfDay(), now()->endOfDay()])->orderBy('inicio')->get();
            foreach ($hoy as $e) {
                $avisos[] = ['critico', 'ki-calendar-tick', "Hoy: {$e->titulo} a las {$e->inicio->format('H:i')}",
                    'Tenga listo el QR de asistencia.', route('asistencia.qr', [Evento::segmentoDe($e->tipo), $e]), 'Ver QR'];
            }

            $sinInvitar = Evento::proximos()->tap($porSede)->where('inicio', '<=', now()->addDays(7))
                ->whereDoesntHave('invitaciones', fn ($q) => $q->where('estado_envio', Invitacion::ENVIADA))->orderBy('inicio')->get();
            foreach ($sinInvitar as $e) {
                $avisos[] = ['advertencia', 'ki-send', "Sin invitaciones: {$e->titulo}",
                    'Es el '.$e->inicio->translatedFormat('l j \d\e F').' y aún no se ha invitado a nadie.', route('eventos.show', [Evento::segmentoDe($e->tipo), $e]).'#invitaciones', 'Invitar'];
            }

            $manana = Evento::proximos()->tap($porSede)
                ->whereBetween('inicio', [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()])
                ->whereNull('recordatorio_enviado_at')
                ->whereHas('invitaciones', fn ($q) => $q->where('estado_envio', Invitacion::ENVIADA))->get();
            foreach ($manana as $e) {
                $avisos[] = ['advertencia', 'ki-notification-on', "Mañana: {$e->titulo}",
                    'Buen momento para enviar el recordatorio.', route('eventos.show', [Evento::segmentoDe($e->tipo), $e]).'#invitaciones', 'Recordar'];
            }

            $sinAsistencia = Evento::whereNull('cancelado_at')->tap($porSede)
                ->whereBetween('fin', [now()->subDays(14), now()])
                ->whereDoesntHave('invitaciones', fn ($q) => $q->whereNotNull('asistio'))->orderByDesc('inicio')->limit(3)->get();
            foreach ($sinAsistencia as $e) {
                $avisos[] = ['info', 'ki-check-square', "Sin asistencia registrada: {$e->titulo}",
                    'Se realizó el '.$e->inicio->translatedFormat('j \d\e F').'.', route('asistencia.show', [Evento::segmentoDe($e->tipo), $e]), 'Registrar'];
            }
        }

        if ($u->can('contactos.ver')) {
            $sinCorreo = Contacto::whereNull('correo')->tap($porSede)->count();
            if ($sinCorreo > 0) {
                $avisos[] = ['info', 'ki-sms', "{$sinCorreo} ".($sinCorreo === 1 ? 'contacto sin correo' : 'contactos sin correo'),
                    'No recibirán invitaciones.', route('contactos.index', ['padres', 'correo' => 'sin']), 'Revisar'];
            }
        }

        if ($u->can('sedes.editar')) {
            $sinDireccion = Sede::activas()->whereNull('direccion')->pluck('nombre');
            if ($sinDireccion->isNotEmpty()) {
                $avisos[] = ['info', 'ki-geolocation', 'Sedes sin dirección: '.$sinDireccion->join(', '),
                    'La dirección aparece en las invitaciones presenciales.', route('sedes.index'), 'Completar'];
            }
        }

        return $avisos;
    }
}
