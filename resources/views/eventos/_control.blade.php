{{-- Control del evento: enviadas vs confirmadas vs no confirmadas vs asistencias vs inasistencias --}}
@php
    $c = $control;
    $pct = fn ($a, $b) => $b ? round($a / $b * 100) : 0;
    $base = max($c['enviadas'], $c['asistencias'], 1);
    $barras = [
        ['Invitaciones enviadas', $c['enviadas'], 'primary', null],
        ['Confirmadas', $c['confirmadas'], 'success', $pct($c['confirmadas'], $c['enviadas']).'% de las enviadas'],
        ['No confirmadas', $c['no_confirmadas'], 'warning', "{$c['sin_respuesta']} sin respuesta · {$c['rechazadas']} dijeron que no"],
        ['Asistencias', $c['asistencias'], 'info', $c['sin_invitacion_asistieron'] ? "incluye {$c['sin_invitacion_asistieron']} sin invitación por correo" : null],
        ['Inasistencias', $c['inasistencias'], 'danger', $pct($c['inasistencias'], $c['enviadas']).'% de los invitados no llegó'],
    ];
@endphp
<div class="card mb-5 mb-xl-8" id="control">
    <div class="card-header border-0 pt-6">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold text-gray-900">Control del evento</span>
            <span class="text-muted mt-1 fw-semibold fs-7">
                @if ($c['asistencia_tomada'])
                    Invitación, respuesta y asistencia
                @else
                    Las asistencias e inasistencias se completan al tomar asistencia
                @endif
            </span>
        </h3>
    </div>
    <div class="card-body pt-2">
        @foreach ($barras as [$etq, $n, $color, $detalle])
            <div class="d-flex align-items-center gap-4 mb-4">
                <div class="w-175px flex-shrink-0">
                    <div class="fw-bold text-gray-800 fs-6">{{ $etq }}</div>
                    @if ($detalle)<div class="text-muted fs-8">{{ $detalle }}</div>@endif
                </div>
                <div class="flex-grow-1 h-20px bg-light rounded overflow-hidden">
                    <div class="bg-{{ $color }} h-20px rounded" style="width: {{ round($n / $base * 100) }}%"></div>
                </div>
                <div class="w-50px text-end fs-3 fw-bold text-{{ $color }}">{{ $n }}</div>
            </div>
        @endforeach

        @if ($c['asistencia_tomada'])
            <div class="separator separator-dashed my-6"></div>
            <div class="row g-4">
                @foreach ([
                    ['Confirmaron y asistieron', $c['confirmaron_y_asistieron'], 'success', 'ki-check-circle'],
                    ['Confirmaron y faltaron', $c['confirmaron_y_faltaron'], 'danger', 'ki-cross-circle'],
                    ['No confirmaron pero asistieron', $c['no_confirmaron_y_asistieron'], 'warning', 'ki-information-5'],
                    ['Llegaron sin invitación', $c['sin_invitacion_asistieron'], 'info', 'ki-user-tick'],
                ] as [$etq, $n, $color, $icono])
                    <div class="col-6 col-lg-3">
                        <div class="border border-dashed border-gray-300 rounded p-4 h-100">
                            <i class="ki-outline {{ $icono }} fs-2 text-{{ $color }}"></i>
                            <div class="fs-2 fw-bold text-gray-900 mt-1">{{ $n }}</div>
                            <div class="text-gray-600 fs-8 fw-semibold">{{ $etq }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if (array_sum($c['por_metodo']) > 0)
                <div class="d-flex flex-wrap gap-3 mt-5 fs-7">
                    <span class="text-muted">Registro de asistencia:</span>
                    @foreach (\App\Models\Invitacion::METODOS as $clave => $etq)
                        @if ($c['por_metodo'][$clave] > 0)
                            <span class="badge badge-light fw-semibold">{{ $etq }}: {{ $c['por_metodo'][$clave] }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
