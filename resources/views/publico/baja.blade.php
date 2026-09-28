@extends('layouts.publico')

@section('title', 'Dejar de recibir correos')

@section('content')
    <div class="card shadow-sm">
        <div class="card-body p-6 p-md-10 text-center">
            @if (session('dado_de_baja') || ! $contacto->acepta_correos)
                <i class="ki-outline ki-check-circle fs-5x text-success mb-5"></i>
                <h1 class="fs-3 fw-bold text-gray-900 mb-3">Listo, ya no recibirá más correos</h1>
                <div class="text-gray-600">
                    Si cambia de opinión, comuníquese con la secretaría del colegio para volver a recibir las invitaciones.
                </div>
            @else
                <i class="ki-outline ki-sms fs-5x text-gray-400 mb-5"></i>
                <h1 class="fs-3 fw-bold text-gray-900 mb-3">¿Dejar de recibir correos?</h1>
                <div class="text-gray-600 mb-8">
                    {{ $contacto->nombre_completo }}, si continúa, el colegio ya no le enviará invitaciones a reuniones ni avisos por correo a
                    <strong>{{ $contacto->correo }}</strong>.
                </div>
                <form method="POST" action="{{ route('invitacion.baja.store', $invitacion->token) }}" class="d-grid d-sm-flex justify-content-center gap-3">
                    @csrf
                    <a href="{{ route('invitacion.show', $invitacion->token) }}" class="btn btn-light">Volver a la invitación</a>
                    <button type="submit" class="btn btn-danger">Sí, no quiero recibir más correos</button>
                </form>
            @endif
        </div>
    </div>
@endsection
