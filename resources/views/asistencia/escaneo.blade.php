@extends('layouts.app')

@php
    $c = $invitacion->contacto;
    $segmento = \App\Models\Evento::segmentoDe($evento->tipo);
    [$icono, $color, $titulo, $texto] = match ($resultado) {
        'registrado' => ['ki-check-circle', 'success', 'Asistencia registrada', null],
        'ya_estaba' => ['ki-information-5', 'primary', 'Ya estaba registrado(a)', 'Su asistencia se había registrado a las '.$invitacion->asistencia_at?->format('H:i').'.'],
        'antes' => ['ki-time', 'warning', 'Todavía no se puede registrar', 'El registro abre '.\App\Models\Evento::REGISTRO_ANTES.' minutos antes del inicio ('.$evento->inicio->translatedFormat('j \d\e F, H:i').').'],
        'terminado' => ['ki-cross-circle', 'danger', 'El registro ya cerró', 'La actividad terminó.'],
        'cancelado' => ['ki-cross-circle', 'danger', 'Actividad cancelada', null],
    };
@endphp

@section('title', 'Escaneo de QR')

@section('content')
    <div class="mw-600px mx-auto">
        <div class="card mb-6 border border-{{ $color }}">
            <div class="card-body text-center p-10">
                <i class="ki-outline {{ $icono }} fs-5x text-{{ $color }} mb-4"></i>
                <h1 class="fs-1 fw-bold text-{{ $color }} mb-4">{{ $titulo }}</h1>
                <div class="fs-2 fw-bold text-gray-900">{{ $c->nombre_completo }}</div>
                <div class="text-gray-600 fs-6 mt-1">
                    {{ $c->tipo === 'padre' ? trim('Padre/madre · '.$c->estudiante.' '.$c->grado_seccion) : trim('Catedrático · '.$c->area) }}
                </div>
                @if ($invitacion->respuesta)
                    <span class="badge badge-light-{{ $invitacion->respuesta === 'confirmada' ? 'success' : 'danger' }} mt-4">
                        {{ $invitacion->respuesta === 'confirmada' ? 'Había confirmado' : 'Había dicho que no asistiría' }}
                    </span>
                @endif
                @if ($texto)<div class="text-gray-600 mt-4">{{ $texto }}</div>@endif
            </div>
        </div>
        <div class="card">
            <div class="card-body d-flex flex-wrap align-items-center gap-4">
                <div class="flex-grow-1">
                    <div class="fw-bold text-gray-900">{{ $evento->titulo }}</div>
                    <div class="text-muted fs-7">{{ $presentes }} {{ $presentes === 1 ? 'persona presente' : 'personas presentes' }}</div>
                </div>
                <a href="{{ route('asistencia.show', [$segmento, $evento]) }}" class="btn btn-sm btn-light">Ver lista</a>
            </div>
            <div class="card-footer text-muted fs-7 py-4">Para el siguiente invitado, escanee su QR con la cámara del celular.</div>
        </div>
    </div>
@endsection
