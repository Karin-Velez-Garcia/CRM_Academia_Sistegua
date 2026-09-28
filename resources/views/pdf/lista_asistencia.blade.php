<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Lista de asistencia</title>
    <style>
        @page { margin: 32px 36px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #252f4a; }
        .enc { width: 100%; border-bottom: 3px solid #1b84ff; padding-bottom: 8px; margin-bottom: 12px; }
        .enc td { vertical-align: middle; }
        .titulo { font-size: 17px; font-weight: bold; color: #071437; }
        .sub { color: #78829d; font-size: 10.5px; margin-top: 2px; }
        table.lista { width: 100%; border-collapse: collapse; }
        table.lista th { background: #1b84ff; color: #fff; text-align: left; padding: 6px 6px; font-size: 10px; }
        table.lista td { border-bottom: 1px solid #dbdfe9; padding: 7px 6px; height: 22px; }
        table.lista tr:nth-child(even) td { background: #f9f9fb; }
        .num { width: 22px; color: #99a1b7; }
        .firma { width: 150px; }
        .chk { width: 55px; text-align: center; }
        .ok { color: #17c653; font-weight: bold; }
        .pie { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 9px; color: #99a1b7; text-align: center; }
    </style>
</head>
<body>
@php($esPadre = $config['publico'] === \App\Models\Contacto::PADRE)
<table class="enc" cellspacing="0" cellpadding="0">
    <tr>
        <td style="width:70px"><img src="{{ public_path('assets/media/logos/esteca-icon.png') }}" style="height:56px" alt=""></td>
        <td>
            <div class="titulo">Lista de asistencia — {{ $evento->titulo }}</div>
            <div class="sub">{{ config('colegio.nombre') }} · Sede {{ $evento->sede->nombre }} · {{ $evento->horario }}</div>
            <div class="sub">{{ $evento->es_virtual ? 'Virtual ('.$evento->plataforma.')' : $evento->lugar }}@if ($evento->facilitador) · Facilitador: {{ $evento->facilitador }}@endif</div>
        </td>
        <td style="text-align:right; width:140px" class="sub">
            {{ $resumen['total'] }} en la lista<br>{{ $resumen['confirmados'] }} confirmaron
        </td>
    </tr>
</table>

<table class="lista">
    <thead>
    <tr>
        <th class="num">#</th>
        <th>{{ $esPadre ? 'Padre o madre' : 'Catedrático' }}</th>
        <th>{{ $esPadre ? 'Estudiante / grado' : 'Curso o área' }}</th>
        <th class="chk">Confirmó</th>
        <th class="firma">Firma</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($lista->values() as $i => $fila)
        @php($c = $fila['contacto'])
        <tr>
            <td class="num">{{ $i + 1 }}</td>
            <td><strong>{{ $c->nombre_completo }}</strong></td>
            <td>{{ $esPadre ? trim($c->estudiante.' '.($c->grado_seccion ? '· '.$c->grado_seccion : '')) : $c->area }}</td>
            <td class="chk">@if ($fila['invitacion']?->respuesta === 'confirmada')<span class="ok">Sí</span>@endif</td>
            <td class="firma"></td>
        </tr>
    @endforeach
    @foreach (range(1, 5) as $extra)
        <tr><td class="num">+</td><td></td><td></td><td></td><td></td></tr>
    @endforeach
    </tbody>
</table>

<div class="pie">Generado el {{ now()->format('d/m/Y H:i') }} · {{ config('colegio.nombre') }}</div>
</body>
</html>
