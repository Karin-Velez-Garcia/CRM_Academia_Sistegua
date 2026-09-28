{{ config('colegio.nombre') }} — Sede {{ $evento->sede->nombre }}

{{ $evento->titulo }}

{!! $mensaje !!}

Fecha y hora: {{ $evento->horario }}
@if ($evento->es_virtual)
Modalidad virtual ({{ $evento->plataforma }}): {{ $evento->enlace }}
@else
Lugar: {{ $evento->lugar }}
@endif
@if ($evento->facilitador)
Facilitador: {{ $evento->facilitador }}
@endif
@if ($conBotones)

Confirmo asistencia: {{ $urlSi }}
No podré asistir: {{ $urlNo }}
@endif

No deseo recibir más correos: {{ $urlBaja }}
