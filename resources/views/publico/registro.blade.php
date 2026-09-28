@extends('layouts.publico')

@section('title', 'Registro de asistencia')
@section('sede', 'Sede '.$evento->sede->nombre)

@section('content')
    @if (session('registrado'))
        @php($r = session('registrado'))
        <div class="card shadow-sm mb-6">
            <div class="card-body p-8 text-center">
                <i class="ki-outline ki-check-circle fs-5x text-success mb-4"></i>
                <h1 class="fs-2 fw-bold text-gray-900 mb-2">{{ $r['ya_estaba'] ? 'Ya estaba registrado(a)' : '¡Asistencia registrada!' }}</h1>
                <div class="fs-5 text-gray-700">{{ $r['nombre'] }}</div>
                <div class="text-muted mt-4">Gracias por acompañarnos en "{{ $evento->titulo }}".</div>
            </div>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-6 p-md-10">
            <span class="badge badge-light-primary fs-8 fw-bold text-uppercase mb-3">Registro de asistencia</span>
            <h1 class="fs-2 fw-bold text-gray-900 mb-2">{{ $evento->titulo }}</h1>
            <div class="text-gray-600 mb-8">{{ $evento->horario }}<br>{{ $evento->es_virtual ? 'Virtual' : $evento->lugar }}</div>

            @if (session('error'))
                <div class="alert bg-light-danger border border-danger border-dashed p-4 mb-6 fw-semibold text-gray-800">{{ session('error') }}</div>
            @endif

            @if ($evento->cancelado_at)
                <div class="alert bg-light-danger p-5 fw-semibold text-gray-800 mb-0">Esta actividad fue cancelada.</div>
            @elseif (! $evento->registro_abierto)
                <div class="alert bg-light-warning p-5 fw-semibold text-gray-800 mb-0">
                    @if ($evento->inicio->isFuture())
                        El registro se abre el {{ $evento->inicio->copy()->subMinutes(\App\Models\Evento::REGISTRO_ANTES)->translatedFormat('j \d\e F \a \l\a\s H:i') }}.
                    @else
                        El registro de esta actividad ya cerró.
                    @endif
                </div>
            @else
                <form method="POST" action="{{ route('registro.registrar', $evento->token_registro) }}">
                    @csrf
                    <label for="identificador" class="form-label fw-bold text-gray-900 fs-5">Su correo electrónico o su DPI</label>
                    <input id="identificador" name="identificador" value="{{ old('identificador') }}" required autofocus autocomplete="email"
                           class="form-control form-control-lg form-control-solid mb-2 @error('identificador') is-invalid @enderror"
                           placeholder="nombre@correo.com o 13 dígitos del DPI">
                    @error('identificador') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                    <div class="text-muted fs-7 mb-6">Use el mismo correo al que le llegó la invitación.</div>
                    <button type="submit" class="btn btn-lg btn-success w-100"><i class="ki-outline ki-check fs-2"></i> Registrar mi asistencia</button>
                </form>
            @endif
        </div>
    </div>
@endsection
