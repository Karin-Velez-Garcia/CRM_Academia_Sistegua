@extends('layouts.app')

@section('title', 'Tablero')

@php
    $pct = fn ($v) => $v === null ? '—' : $v.'%';
    $hayDatos = $totales['enviadas'] > 0 || $totales['asistencias'] > 0;
    $respuestas = [
        ['Confirmaron', $totales['confirmadas'], 'success', 'ki-check-circle'],
        ['Dijeron que no', $totales['rechazadas'], 'danger', 'ki-cross-circle'],
        ['Sin respuesta', $totales['sin_respuesta'], 'warning', 'ki-time'],
    ];
    $colorAviso = ['critico' => 'danger', 'advertencia' => 'warning', 'info' => 'primary'];
@endphp

@section('acciones')
    <form method="GET" class="d-flex flex-wrap gap-2" data-filtros-tablero>
        <select name="periodo" class="form-select form-select-sm form-select-solid w-auto" aria-label="Período">
            @foreach (\App\Http\Controllers\DashboardController::PERIODOS as $valor => $etq)
                <option value="{{ $valor }}" @selected($periodo === (string) $valor)>{{ $etq }}</option>
            @endforeach
        </select>
        @if ($sedes->count() > 1)
            <select name="sede" class="form-select form-select-sm form-select-solid w-auto" aria-label="Sede">
                <option value="">Todas las sedes</option>
                @foreach ($sedes as $s)
                    <option value="{{ $s->id }}" @selected($sedeId === $s->id)>{{ $s->nombre }}</option>
                @endforeach
            </select>
        @endif
    </form>
@endsection

@section('content')
    {{-- Indicadores clave --}}
    <div class="row g-5 mb-5">
        @foreach ([
            ['Padres de familia', number_format($kpi['padres']), 'registrados', 'ki-people', route('contactos.index', 'padres')],
            ['Catedráticos', number_format($kpi['catedraticos']), 'registrados', 'ki-teacher', route('contactos.index', 'catedraticos')],
            ['Próximos 30 días', $kpi['proximos_30'], $kpi['proximos_30'] === 1 ? 'evento programado' : 'eventos programados', 'ki-calendar', null],
            ['Confirmación', $pct($kpi['tasa_confirmacion']), 'de '.number_format($totales['enviadas']).' invitaciones enviadas', 'ki-check-circle', null],
            ['Asistencia', $pct($kpi['tasa_asistencia']), 'de los invitados en '.$kpi['realizados'].' '.($kpi['realizados'] === 1 ? 'evento' : 'eventos'), 'ki-user-tick', null],
        ] as [$titulo, $valor, $detalle, $icono, $enlace])
            <div class="col-sm-6 col-xl">
                <div class="card h-100 position-relative">
                    @if ($enlace && auth()->user()->can('contactos.ver'))
                        <a href="{{ $enlace }}" class="stretched-link" aria-label="{{ $titulo }}"></a>
                    @endif
                    <div class="card-body py-6">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-gray-600 fw-semibold fs-7">{{ $titulo }}</span>
                            <i class="ki-outline {{ $icono }} fs-2 text-gray-500"></i>
                        </div>
                        <div class="fs-2hx fw-bold text-gray-900 lh-1 mb-2">{{ $valor }}</div>
                        <div class="text-muted fs-8">{{ $detalle }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-5 mb-5">
        {{-- Gráfica por mes --}}
        <div class="col-xl-8">
            <div class="card h-100 viz-root">
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-gray-900">Invitaciones y asistencia por mes</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">Eventos ya realizados · {{ \App\Http\Controllers\DashboardController::PERIODOS[$periodo] }}</span>
                    </h3>
                    @if ($hayDatos)
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-light" data-alternar-tabla aria-pressed="false">Ver tabla</button>
                        </div>
                    @endif
                </div>
                <div class="card-body pt-0">
                    @if ($hayDatos)
                        <div class="d-flex flex-wrap gap-5 mb-2 fs-7 fw-semibold text-gray-700" aria-hidden="true">
                            <span><span class="leyenda-punto" style="background: var(--series-1)"></span>Invitaciones enviadas</span>
                            <span><span class="leyenda-punto" style="background: var(--series-2)"></span>Confirmadas</span>
                            <span><span class="leyenda-punto" style="background: var(--series-3)"></span>Asistencias</span>
                        </div>
                        <div id="graficaMeses" class="min-h-300px" role="img"
                             aria-label="Gráfica de barras por mes con invitaciones enviadas, confirmadas y asistencias"></div>
                        <div class="table-responsive" data-tabla-meses hidden>
                            <table class="table table-row-dashed fs-7 gy-2 mb-0">
                                <thead><tr class="text-muted fw-bold text-uppercase fs-8">
                                    <th>Mes</th><th class="text-end">Eventos</th><th class="text-end">Enviadas</th><th class="text-end">Confirmadas</th><th class="text-end">Asistencias</th>
                                </tr></thead>
                                <tbody class="fw-semibold text-gray-700">
                                @foreach ($porMes as $m)
                                    <tr>
                                        <td>{{ $m['etiqueta'] }}</td>
                                        <td class="text-end">{{ $m['eventos'] }}</td>
                                        <td class="text-end">{{ $m['enviadas'] }}</td>
                                        <td class="text-end">{{ $m['confirmadas'] }}</td>
                                        <td class="text-end">{{ $m['asistencias'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="d-flex flex-column flex-center text-center min-h-300px">
                            <i class="ki-outline ki-chart-simple fs-5x text-gray-300 mb-4"></i>
                            <div class="text-gray-700 fw-semibold mb-1">Todavía no hay eventos realizados en este período.</div>
                            <div class="text-muted fs-7">La gráfica se llena cuando se envían invitaciones y se toma asistencia.</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Requiere atención --}}
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-gray-900">Requiere atención</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">{{ count($avisos) ? count($avisos).' '.(count($avisos) === 1 ? 'pendiente' : 'pendientes') : 'Todo al día' }}</span>
                    </h3>
                </div>
                <div class="card-body pt-2">
                    @forelse ($avisos as [$nivel, $icono, $titulo, $detalle, $url, $accion])
                        @php($color = $colorAviso[$nivel])
                        <div class="d-flex align-items-start gap-3 py-3 {{ ! $loop->last ? 'border-bottom border-gray-200 border-bottom-dashed' : '' }}">
                            <span class="symbol symbol-35px flex-shrink-0">
                                <span class="symbol-label bg-light-{{ $color }}"><i class="ki-outline {{ $icono }} fs-3 text-{{ $color }}"></i></span>
                            </span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-bold text-gray-800 fs-7">{{ $titulo }}</div>
                                <div class="text-muted fs-8">{{ $detalle }}</div>
                            </div>
                            <a href="{{ $url }}" class="btn btn-sm btn-light btn-active-light-primary flex-shrink-0 py-1 px-3 fs-8">{{ $accion }}</a>
                        </div>
                    @empty
                        <div class="d-flex flex-column flex-center text-center py-10">
                            <i class="ki-outline ki-check-circle fs-4x text-success mb-3"></i>
                            <div class="text-gray-700 fw-semibold">No hay pendientes.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-5">
        {{-- Respuestas --}}
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-gray-900">Respuestas a las invitaciones</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">{{ number_format($totales['enviadas']) }} invitaciones de eventos realizados</span>
                    </h3>
                </div>
                <div class="card-body pt-2">
                    @foreach ($respuestas as [$etq, $n, $color, $icono])
                        @php($p = $totales['enviadas'] ? round($n / $totales['enviadas'] * 100) : 0)
                        <div class="mb-6">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-semibold text-gray-800 fs-6"><i class="ki-outline {{ $icono }} fs-4 text-{{ $color }} me-2"></i>{{ $etq }}</span>
                                <span class="fw-bold text-gray-900">{{ number_format($n) }} <span class="text-muted fw-semibold fs-7 ms-1">{{ $p }}%</span></span>
                            </div>
                            <div class="h-10px bg-light rounded overflow-hidden"><div class="bg-{{ $color }} h-10px rounded" style="width: {{ $p }}%"></div></div>
                        </div>
                    @endforeach
                    <div class="separator separator-dashed my-5"></div>
                    <div class="d-flex justify-content-between fs-7">
                        <span class="text-gray-600 fw-semibold">Asistencias registradas</span>
                        <span class="fw-bold text-gray-900">{{ number_format($totales['asistencias']) }}</span>
                    </div>
                    <div class="d-flex justify-content-between fs-7 mt-2">
                        <span class="text-gray-600 fw-semibold">Invitados que no llegaron</span>
                        <span class="fw-bold text-gray-900">{{ number_format(max(0, $totales['enviadas'] - $totales['asistencias_invitados'])) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Asistencia por sede --}}
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-gray-900">Asistencia por sede</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">% de invitados que asistieron</span>
                    </h3>
                </div>
                <div class="card-body pt-2">
                    @forelse ($porSede as $s)
                        <div class="mb-6">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-semibold text-gray-800 fs-6">{{ $s['sede'] }}</span>
                                <span class="fw-bold text-gray-900">{{ $pct($s['tasa']) }}</span>
                            </div>
                            <div class="h-10px bg-light rounded overflow-hidden"><div class="bg-primary h-10px rounded" style="width: {{ $s['tasa'] ?? 0 }}%"></div></div>
                            <div class="text-muted fs-8 mt-1">{{ $s['eventos'] }} {{ $s['eventos'] === 1 ? 'evento' : 'eventos' }} · {{ $s['asistencias'] }} asistencias de {{ $s['enviadas'] }} invitados</div>
                        </div>
                    @empty
                        <div class="text-muted py-6">Sin eventos realizados en el período.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Próximos eventos --}}
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-gray-900">Próximos eventos</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">Confirmaciones hasta ahora</span>
                    </h3>
                    @can('eventos.crear')
                        <div class="card-toolbar">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="dropdown" aria-expanded="false"><i class="ki-outline ki-plus fs-4"></i> Nuevo</button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item fs-7" href="{{ route('eventos.create', 'reuniones') }}">Reunión</a>
                                    <a class="dropdown-item fs-7" href="{{ route('eventos.create', 'capacitaciones') }}">Capacitación</a>
                                </div>
                            </div>
                        </div>
                    @endcan
                </div>
                <div class="card-body pt-2">
                    @forelse ($proximos as $e)
                        @php($p = $e->enviadas_count ? round($e->confirmadas_count / $e->enviadas_count * 100) : 0)
                        <a href="{{ route('eventos.show', [\App\Models\Evento::segmentoDe($e->tipo), $e]) }}" class="d-flex align-items-center gap-3 py-3 evento-fila rounded px-2 {{ ! $loop->last ? 'border-bottom border-gray-200 border-bottom-dashed' : '' }}">
                            <div class="evento-fecha text-center rounded bg-light-primary px-2 py-1 flex-shrink-0">
                                <div class="fs-9 fw-bold text-uppercase text-primary">{{ $e->inicio->translatedFormat('M') }}</div>
                                <div class="fs-3 fw-bolder text-gray-900 lh-1">{{ $e->inicio->format('d') }}</div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-bold text-gray-900 fs-7 text-truncate">{{ $e->titulo }}</div>
                                <div class="text-muted fs-8">{{ $e->inicio->format('H:i') }} · {{ $e->sede->nombre }} · {{ $e->inicio->diffForHumans() }}</div>
                                @if ($e->enviadas_count)
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <div class="h-5px bg-light rounded flex-grow-1 overflow-hidden"><div class="bg-success h-5px" style="width: {{ $p }}%"></div></div>
                                        <span class="fs-9 fw-semibold text-gray-600 text-nowrap">{{ $e->confirmadas_count }}/{{ $e->enviadas_count }} confirmaron</span>
                                    </div>
                                @else
                                    <div class="fs-9 fw-semibold text-warning mt-1"><i class="ki-outline ki-information-5 fs-8 text-warning"></i> Sin invitaciones</div>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="text-muted py-6">No hay eventos programados.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Últimos eventos realizados --}}
    @if ($recientes->isNotEmpty())
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title fw-bold">Últimos eventos realizados</h3>
                @can('reportes.ver')
                    <div class="card-toolbar"><a href="{{ route('reportes.eventos') }}" class="btn btn-sm btn-light">Ver reportes</a></div>
                @endcan
            </div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7 gy-3 mb-0">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-8">
                            <th>Fecha</th><th class="min-w-200px">Evento</th><th>Sede</th>
                            <th class="text-end">Enviadas</th><th class="text-end">Confirmadas</th><th class="text-end">Asistencias</th><th class="w-150px">Asistencia</th>
                        </tr></thead>
                        <tbody class="fw-semibold text-gray-700">
                        @foreach ($recientes as $e)
                            @php($t = \App\Http\Controllers\DashboardController::tasa($e->asistencias_invitados_count, $e->enviadas_count))
                            <tr>
                                <td class="text-nowrap">{{ $e->inicio->format('d/m/Y') }}</td>
                                <td><a href="{{ route('eventos.show', [\App\Models\Evento::segmentoDe($e->tipo), $e]) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $e->titulo }}</a>
                                    <span class="d-block text-muted fs-8">{{ $e->tipo === 'reunion' ? 'Reunión' : 'Capacitación' }}</span></td>
                                <td>{{ $e->sede->nombre }}</td>
                                <td class="text-end">{{ $e->enviadas_count }}</td>
                                <td class="text-end">{{ $e->confirmadas_count }}</td>
                                <td class="text-end text-gray-900 fw-bold">{{ $e->asistencias_count }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="h-6px bg-light rounded flex-grow-1 overflow-hidden"><div class="bg-primary h-6px" style="width: {{ $t ?? 0 }}%"></div></div>
                                        <span class="fs-8 fw-bold text-gray-800 w-35px text-end">{{ $pct($t) }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('styles')
    <style>
        /* Paleta de la gráfica (validada para daltonismo con la guía dataviz) */
        .viz-root { --series-1: #2a78d6; --series-2: #eb6834; --series-3: #1baf7a; --viz-grid: #e1e0d9; --viz-muted: #78829d; --viz-surface: #ffffff; }
        [data-bs-theme="dark"] .viz-root { --series-1: #3987e5; --series-2: #d95926; --series-3: #199e70; --viz-grid: #2c2c2a; --viz-muted: #898781; --viz-surface: #1e1e2d; }
        .leyenda-punto { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
        .fs-9 { font-size: .7rem !important; }
        .min-w-0 { min-width: 0 !important; }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/apexcharts.min.js') }}"></script>
    <script>
        (function () {
            // Filtros: se aplican al cambiar
            document.querySelectorAll('[data-filtros-tablero] select').forEach(function (s) {
                s.addEventListener('change', function () { s.form.submit(); });
            });

            var datos = @json($porMes);
            var el = document.getElementById('graficaMeses');
            if (!el || !window.ApexCharts) return;

            var boton = document.querySelector('[data-alternar-tabla]');
            var tabla = document.querySelector('[data-tabla-meses]');
            boton.addEventListener('click', function () {
                var verTabla = tabla.hidden;
                tabla.hidden = !verTabla;
                el.hidden = verTabla;
                boton.textContent = verTabla ? 'Ver gráfica' : 'Ver tabla';
                boton.setAttribute('aria-pressed', verTabla ? 'true' : 'false');
            });

            var grafica;
            function css(v) { return getComputedStyle(el.closest('.viz-root')).getPropertyValue(v).trim(); }
            function dibujar() {
                if (grafica) grafica.destroy();
                var oscuro = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                grafica = new ApexCharts(el, {
                    chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit', background: 'transparent', animations: { enabled: false } },
                    theme: { mode: oscuro ? 'dark' : 'light' },
                    series: [
                        { name: 'Invitaciones enviadas', data: datos.map(function (m) { return m.enviadas; }) },
                        { name: 'Confirmadas', data: datos.map(function (m) { return m.confirmadas; }) },
                        { name: 'Asistencias', data: datos.map(function (m) { return m.asistencias; }) }
                    ],
                    colors: [css('--series-1'), css('--series-2'), css('--series-3')],
                    plotOptions: { bar: { columnWidth: '58%', borderRadius: 4, borderRadiusApplication: 'end' } },
                    stroke: { show: true, width: 2, colors: [css('--viz-surface')] },
                    dataLabels: { enabled: false },
                    legend: { show: false },
                    xaxis: {
                        categories: datos.map(function (m) { return m.etiqueta; }),
                        axisBorder: { show: false }, axisTicks: { show: false },
                        labels: { style: { colors: css('--viz-muted'), fontSize: '12px' } }
                    },
                    yaxis: { min: 0, forceNiceScale: true, labels: { style: { colors: css('--viz-muted'), fontSize: '12px' }, formatter: function (v) { return Math.round(v); } } },
                    grid: { borderColor: css('--viz-grid'), strokeDashArray: 3, yaxis: { lines: { show: true } }, xaxis: { lines: { show: false } } },
                    tooltip: {
                        shared: true, intersect: false,
                        x: { formatter: function (v, o) { var m = datos[o.dataPointIndex]; return v + ' · ' + m.eventos + (m.eventos === 1 ? ' evento' : ' eventos'); } }
                    }
                });
                grafica.render();
            }
            dibujar();
            // Volver a dibujar con los colores del modo claro u oscuro al cambiar el tema
            new MutationObserver(dibujar).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
        })();
    </script>
@endpush
