@extends('layouts.app')

@section('title', 'Plantillas de correo')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted">Comunicación</li>
@endsection

@section('acciones')
    @can('campanias.crear')
        <a href="{{ route('plantillas.create') }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-plus fs-2"></i> Nueva plantilla</a>
    @endcan
@endsection

@section('content')
    <div class="row g-5 g-xl-8">
        @foreach ($plantillas as $p)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title flex-column">
                            <h3 class="fw-bold mb-1">{{ $p->nombre }}</h3>
                            <span class="text-muted fs-7 fw-semibold">{{ $p->tipo_evento === 'reunion' ? 'Reuniones con padres' : 'Capacitaciones para catedráticos' }}</span>
                        </div>
                        @if ($p->predeterminada)
                            <div class="card-toolbar"><span class="badge badge-light-primary">Predeterminada</span></div>
                        @endif
                    </div>
                    <div class="card-body pt-2">
                        <div class="text-gray-500 fs-8 fw-bold text-uppercase mb-1">Asunto</div>
                        <div class="text-gray-900 fw-semibold mb-4">{{ $p->asunto }}</div>
                        <div class="text-gray-500 fs-8 fw-bold text-uppercase mb-1">Mensaje</div>
                        <div class="text-gray-700 fs-7" style="white-space: pre-line">{{ \Illuminate\Support\Str::limit($p->mensaje, 400) }}</div>
                    </div>
                    <div class="card-footer d-flex gap-2 pt-0 border-0">
                        @can('campanias.editar')
                            <a href="{{ route('plantillas.edit', $p) }}" class="btn btn-sm btn-light btn-active-light-primary">Editar</a>
                        @endcan
                        @can('campanias.eliminar')
                            <form method="POST" action="{{ route('plantillas.destroy', $p) }}" data-confirmar="¿Eliminar la plantilla {{ $p->nombre }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-light btn-active-light-danger">Eliminar</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
