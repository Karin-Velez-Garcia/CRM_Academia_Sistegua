@extends('layouts.app')

@php($pct = fn ($a, $b) => \App\Http\Controllers\ReporteController::porcentaje($a, $b))

@section('title', 'Reportes')

@section('content')
    @include('reportes._pestanas')

    <div class="card mb-8">
        <div class="card-body py-5">
            @include('reportes._filtros', [
                'tipos' => ['reunion' => 'Reuniones', 'capacitacion' => 'Capacitaciones'],
                'rutaExcel' => route('reportes.eventos.excel', request()->query()),
                'extra' => new \Illuminate\Support\HtmlString(
                    '<label class="form-check form-check-sm form-check-custom form-check-solid mb-2"><input class="form-check-input" type="checkbox" name="cancelados" value="1" '
                    .(request()->boolean('cancelados') ? 'checked' : '').'><span class="form-check-label fs-7">Incluir cancelados</span></label>'),
            ])
        </div>
    </div>

    <div class="row g-5 g-xl-8 mb-8">
        @foreach ([
            ['Eventos', $totales['eventos'], null, 'ki-calendar', 'primary'],
            ['Invitados', $totales['invitados'], null, 'ki-sms', 'info'],
            ['Confirmaron', $totales['confirmados'], $pct($totales['confirmados'], $totales['invitados']).' de los invitados', 'ki-check-circle', 'success'],
            ['Asistieron', $totales['asistentes'], $pct($totales['asistentes'], $totales['invitados']).' de los invitados', 'ki-people', 'warning'],
        ] as [$etq, $n, $detalle, $icono, $color])
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100"><div class="card-body d-flex align-items-center">
                    <div class="symbol symbol-50px me-5"><span class="symbol-label bg-light-{{ $color }}"><i class="ki-outline {{ $icono }} fs-2x text-{{ $color }}"></i></span></div>
                    <div>
                        <div class="fs-2hx fw-bold text-gray-900 lh-1">{{ number_format($n) }}</div>
                        <div class="fw-semibold text-gray-700 mt-1">{{ $etq }}</div>
                        @if ($detalle)<div class="fs-7 text-muted">{{ $detalle }}</div>@endif
                    </div>
                </div></div>
            </div>
        @endforeach
    </div>

    @if ($porSede->count() > 1)
        <div class="card mb-8">
            <div class="card-header border-0 pt-6"><h3 class="card-title fw-bold">Por sede</h3></div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-6 gy-3 mb-0">
                        <thead><tr class="text-muted fw-bold fs-7 text-uppercase">
                            <th>Sede</th><th class="text-end">Eventos</th><th class="text-end">Invitados</th><th class="text-end">Confirmaron</th><th class="text-end">Asistieron</th><th class="w-200px">Asistencia</th>
                        </tr></thead>
                        <tbody class="fw-semibold text-gray-700">
                        @foreach ($porSede as $sede => $t)
                            @php($p = $t['invitados'] ? round($t['asistentes'] / $t['invitados'] * 100) : 0)
                            <tr>
                                <td class="text-gray-900 fw-bold">{{ $sede }}</td>
                                <td class="text-end">{{ $t['eventos'] }}</td>
                                <td class="text-end">{{ number_format($t['invitados']) }}</td>
                                <td class="text-end">{{ number_format($t['confirmados']) }}</td>
                                <td class="text-end">{{ number_format($t['asistentes']) }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="h-6px bg-light rounded flex-grow-1"><div class="bg-success rounded h-6px" style="width: {{ $p }}%"></div></div>
                                        <span class="fs-7 fw-bold">{{ $t['invitados'] ? $p.'%' : '—' }}</span>
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

    <div class="card">
        <div class="card-header border-0 pt-6">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-gray-900">Eventos del período</span>
                <span class="text-muted mt-1 fw-semibold fs-7">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</span>
            </h3>
        </div>
        <div class="card-body pt-2">
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle fs-6 gy-3">
                    <thead><tr class="text-muted fw-bold fs-7 text-uppercase">
                        <th>Fecha</th><th class="min-w-250px">Evento</th><th>Sede</th>
                        <th class="text-end">Enviadas</th><th class="text-end">Confirmadas</th><th class="text-end">No confirmadas</th><th class="text-end">Asistencias</th><th class="text-end">Inasistencias</th><th class="text-end">% asistencia</th>
                    </tr></thead>
                    <tbody class="fw-semibold text-gray-700">
                    @forelse ($eventos as $e)
                        <tr>
                            <td class="text-nowrap">{{ $e->inicio->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('eventos.show', [\App\Models\Evento::segmentoDe($e->tipo), $e]) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $e->titulo }}</a>
                                <div class="fs-7 text-muted">
                                    {{ $e->tipo === 'reunion' ? 'Reunión' : 'Capacitación' }} · {{ $e->es_virtual ? 'Virtual' : 'Presencial' }}
                                    @if ($e->cancelado_at)<span class="badge badge-light-danger ms-1">Cancelado</span>@endif
                                </div>
                            </td>
                            <td>{{ $e->sede->nombre }}</td>
                            <td class="text-end">{{ $e->invitados_count }}</td>
                            <td class="text-end text-success">{{ $e->confirmados_count }}</td>
                            <td class="text-end text-warning">{{ $e->invitados_count - $e->confirmados_count }}</td>
                            <td class="text-end fw-bold text-gray-900">{{ $e->asistentes_count }}</td>
                            <td class="text-end text-danger">{{ $e->invitados_count - $e->asistentes_invitados_count }}</td>
                            <td class="text-end">{{ $pct($e->asistentes_count, $e->invitados_count) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-10">No hay eventos en este período.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="text-muted fs-8">El % de asistencia compara a quienes asistieron con los invitados por correo; quienes llegaron sin invitación cuentan como asistentes.</div>
        </div>
    </div>
@endsection
