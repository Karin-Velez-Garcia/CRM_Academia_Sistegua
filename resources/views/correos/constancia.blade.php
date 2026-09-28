@php
    $logo = isset($message) && method_exists($message, 'embed')
        ? $message->embed(public_path('assets/media/logos/esteca-icon.png'))
        : asset('assets/media/logos/esteca-icon.png');
@endphp
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Constancia</title></head>
<body style="margin:0; padding:0; background:#f1f3f8; font-family:Arial, Helvetica, sans-serif; color:#252f4a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px; background:#fff; border-radius:12px; overflow:hidden;">
            <tr><td style="background:#1b84ff; padding:20px 28px; color:#fff;">
                <table role="presentation" cellspacing="0" cellpadding="0"><tr>
                    <td style="background:#fff; border-radius:8px; padding:4px;"><img src="{{ $logo }}" width="44" height="44" alt="" style="display:block;"></td>
                    <td style="padding-left:14px;"><div style="font-size:18px; font-weight:bold;">{{ config('colegio.nombre') }}</div>
                        <div style="font-size:13px; opacity:.85;">Sede {{ $evento->sede->nombre }}</div></td>
                </tr></table>
            </td></tr>
            <tr><td style="padding:28px; font-size:15px; line-height:1.6; color:#4b5675;">
                <p style="margin-top:0;">Estimado(a) {{ $contacto->nombre_completo }}:</p>
                <p>Gracias por participar en la capacitación <strong style="color:#071437;">"{{ $evento->titulo }}"</strong>
                    del {{ $evento->inicio->translatedFormat('j \d\e F \d\e Y') }}. Adjuntamos su constancia de participación en PDF.</p>
                <p style="font-size:13px; color:#99a1b7;">Código de verificación: <strong>{{ $codigo }}</strong> —
                    <a href="{{ route('constancia.verificar', $codigo) }}" style="color:#1b84ff;">verificar constancia</a></p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
