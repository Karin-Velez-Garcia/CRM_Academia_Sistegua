<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia de participación</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #252f4a; }
        .pagina { position: relative; width: 100%; height: 100%; page-break-after: always; }
        .pagina:last-child { page-break-after: auto; }
        .marco { position: absolute; top: 28px; left: 28px; right: 28px; bottom: 28px; border: 3px solid #1b84ff; }
        .marco-int { position: absolute; top: 36px; left: 36px; right: 36px; bottom: 36px; border: 1px solid #c9dcff; }
        .franja { position: absolute; top: 36px; left: 36px; right: 36px; height: 10px; background: #1b84ff; }
        .franja-roja { position: absolute; top: 46px; left: 36px; right: 36px; height: 4px; background: #e4252f; }
        .contenido { position: absolute; top: 80px; left: 90px; right: 90px; text-align: center; }
        .logo { height: 110px; }
        .colegio { font-size: 15px; letter-spacing: 3px; text-transform: uppercase; color: #78829d; margin-top: 8px; }
        h1 { font-size: 38px; margin: 18px 0 4px; color: #071437; letter-spacing: 1px; }
        .otorga { font-size: 15px; color: #78829d; margin-top: 14px; }
        .nombre { font-size: 32px; font-weight: bold; color: #1b84ff; margin: 10px 0 6px; padding-bottom: 8px; border-bottom: 1px solid #dbdfe9; display: inline-block; min-width: 60%; }
        .texto { font-size: 15px; line-height: 1.6; color: #4b5675; margin: 14px 40px 0; }
        .texto strong { color: #071437; }
        .firmas { position: absolute; bottom: 125px; left: 150px; width: 756px; }
        .firma { width: 330px; text-align: center; font-size: 12px; color: #4b5675; border-top: 1px solid #252f4a; padding-top: 6px; }
        .verif { position: absolute; bottom: 48px; left: 0; right: 0; text-align: center; font-size: 10px; color: #99a1b7; }
    </style>
</head>
<body>
@foreach ($invitaciones as $inv)
    <div class="pagina">
        <div class="marco"></div>
        <div class="marco-int"></div>
        <div class="franja"></div>
        <div class="franja-roja"></div>

        <div class="contenido">
            <img class="logo" src="{{ public_path('assets/media/logos/esteca-logo.png') }}" alt="">
            <div class="colegio">{{ config('colegio.nombre') }} · Sede {{ $evento->sede->nombre }}</div>
            <h1>CONSTANCIA DE PARTICIPACIÓN</h1>
            <div class="otorga">Se otorga la presente a</div>
            <div class="nombre">{{ $inv->contacto->nombre_completo }}</div>
            <div class="texto">
                por su participación en la capacitación <strong>"{{ $evento->titulo }}"</strong>,
                realizada el {{ $evento->inicio->translatedFormat('j \d\e F \d\e Y') }}
                {{ $evento->es_virtual ? 'en modalidad virtual' : 'en '.$evento->sede->municipio->nombre.', '.$evento->sede->municipio->departamento->nombre }},
                con una duración de <strong>{{ $evento->duracion_texto }}</strong>@if ($evento->facilitador), impartida por <strong>{{ $evento->facilitador }}</strong>@endif.
            </div>
        </div>

        <table class="firmas" cellspacing="0" cellpadding="0">
            <tr>
                <td class="firma">{{ $evento->facilitador ?: 'Facilitador(a)' }}<br><span style="color:#99a1b7">Facilitador(a)</span></td>
                <td style="width:96px"></td>
                <td class="firma">Dirección<br><span style="color:#99a1b7">{{ config('colegio.nombre') }}, sede {{ $evento->sede->nombre }}</span></td>
            </tr>
        </table>

        <div class="verif">
            Código de verificación: <strong>{{ $inv->codigo_constancia }}</strong> · Verifique en {{ route('constancia.verificar', $inv->codigo_constancia) }}
        </div>
    </div>
@endforeach
</body>
</html>
