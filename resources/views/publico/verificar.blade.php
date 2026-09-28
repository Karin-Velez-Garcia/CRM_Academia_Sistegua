@extends('layouts.publico')

@section('title', 'Verificar constancia')

@section('content')
    <div class="card shadow-sm mb-6">
        <div class="card-body p-6 p-md-10">
            <h1 class="fs-3 fw-bold text-gray-900 mb-2">Verificar constancia</h1>
            <div class="text-gray-600 mb-6">Escriba el código que aparece en la parte inferior de la constancia.</div>
            <form method="GET" action="{{ route('constancia.verificar') }}" class="d-flex gap-3">
                <input name="codigo" value="{{ $codigo }}" class="form-control form-control-solid text-uppercase" placeholder="ABCD-EFGH" maxlength="12" required>
                <button type="submit" class="btn btn-primary">Verificar</button>
            </form>
        </div>
    </div>

    @if ($codigo)
        <div class="card shadow-sm">
            <div class="card-body p-6 p-md-10">
                @if ($invitacion)
                    @php($e = $invitacion->evento)
                    <div class="d-flex align-items-center mb-6">
                        <i class="ki-outline ki-shield-tick fs-3x text-success me-4"></i>
                        <div>
                            <div class="fs-4 fw-bold text-success">Constancia válida</div>
                            <div class="text-muted fs-7">Código {{ $codigo }}</div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-3 fs-6">
                        <div><span class="text-muted">Participante:</span> <strong class="text-gray-900">{{ $invitacion->contacto->nombre_completo }}</strong></div>
                        <div><span class="text-muted">Capacitación:</span> <strong class="text-gray-900">{{ $e->titulo }}</strong></div>
                        <div><span class="text-muted">Fecha:</span> {{ $e->inicio->translatedFormat('j \d\e F \d\e Y') }} · {{ $e->duracion_texto }}</div>
                        <div><span class="text-muted">Sede:</span> {{ $e->sede->nombre }}</div>
                        @if ($e->facilitador)<div><span class="text-muted">Facilitador:</span> {{ $e->facilitador }}</div>@endif
                    </div>
                @else
                    <div class="d-flex align-items-center">
                        <i class="ki-outline ki-cross-circle fs-3x text-danger me-4"></i>
                        <div>
                            <div class="fs-4 fw-bold text-danger">Código no encontrado</div>
                            <div class="text-gray-600">No existe una constancia con el código {{ $codigo }}. Revise que esté bien escrito.</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
