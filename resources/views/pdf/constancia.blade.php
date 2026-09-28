@php
    use App\Models\PlantillaCertificado;

    $medidas = $plantilla->medidas();
    $anchoPt = $medidas['ancho'];
    $altoPt = $medidas['alto'];

    // Las coordenadas se guardan en % de la página; aquí se pasan a puntos,
    // que es lo único que dompdf posiciona de forma fiable.
    $x = fn ($v) => round($v * $anchoPt / 100, 2);
    $y = fn ($v) => round($v * $altoPt / 100, 2);

    $rutaImagen = function (string $src) {
        if ($src === 'logo') {
            return public_path('assets/media/logos/academia-logo.png');
        }
        $ruta = storage_path('app/certificados/'.basename($src));

        return is_file($ruta) ? $ruta : null;
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia de participación</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; }
        .pagina { position: relative; width: {{ $anchoPt }}pt; height: {{ $altoPt }}pt; overflow: hidden; }
        .salto { page-break-after: always; }
        .el { position: absolute; }
    </style>
</head>
<body>
@foreach ($invitaciones as $i => $inv)
    <div class="pagina {{ $i < count($invitaciones) - 1 ? 'salto' : '' }}">
        @if ($plantilla->fondo && ($fondo = $rutaImagen($plantilla->fondo)))
            <img class="el" src="{{ $fondo }}" style="left:0; top:0; width:{{ $anchoPt }}pt; height:{{ $altoPt }}pt;" alt="">
        @endif

        @foreach ($plantilla->elementos as $el)
            @php
                $izq = $x($el['x']);
                $arr = $y($el['y']);
                $ancho = $x($el['ancho']);
            @endphp

            @if ($el['tipo'] === 'texto')
                @php
                    $texto = PlantillaCertificado::rellenar($el['texto'] ?? '', $evento, $inv);
                    $texto = ($el['mayusculas'] ?? false) ? mb_strtoupper($texto) : $texto;
                @endphp
                <div class="el" style="left:{{ $izq }}pt; top:{{ $arr }}pt; width:{{ $ancho }}pt;
                        font-size:{{ $el['fuente'] }}pt; line-height:1.4; color:{{ $el['color'] }};
                        text-align:{{ $el['alineacion'] }};
                        font-weight:{{ ($el['negrita'] ?? false) ? 'bold' : 'normal' }};
                        font-style:{{ ($el['cursiva'] ?? false) ? 'italic' : 'normal' }};">{{ $texto }}</div>

            @elseif ($el['tipo'] === 'linea')
                <div class="el" style="left:{{ $izq }}pt; top:{{ $arr }}pt; width:{{ $ancho }}pt;
                        height:{{ $el['grosor'] }}pt; background-color:{{ $el['color'] }};"></div>

            @elseif ($el['tipo'] === 'marco')
                <div class="el" style="left:{{ $izq }}pt; top:{{ $arr }}pt; width:{{ $ancho }}pt;
                        height:{{ $y($el['alto']) }}pt; border:{{ $el['grosor'] }}pt solid {{ $el['color'] }};"></div>

            @elseif ($el['tipo'] === 'imagen' && ($ruta = $rutaImagen($el['src'] ?? 'logo')))
                <img class="el" src="{{ $ruta }}" alt=""
                     style="left:{{ $izq }}pt; top:{{ $arr }}pt; width:{{ $ancho }}pt;
                        @if (! empty($el['alto'])) height:{{ $y($el['alto']) }}pt; @endif">
            @endif
        @endforeach
    </div>
@endforeach
</body>
</html>
