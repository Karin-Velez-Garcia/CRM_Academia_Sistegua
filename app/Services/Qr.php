<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;

/**
 * Genera códigos QR en PNG (como data URI para vistas y PDF, o binario para adjuntar al correo).
 */
class Qr
{
    public static function dataUri(string $texto, int $escala = 8): string
    {
        return (new QRCode(self::opciones($escala, true)))->render($texto);
    }

    public static function png(string $texto, int $escala = 8): string
    {
        return (new QRCode(self::opciones($escala, false)))->render($texto);
    }

    private static function opciones(int $escala, bool $base64): QROptions
    {
        return new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => $escala,
            'quietzoneSize' => 2,
            'outputBase64' => $base64,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
        ]);
    }
}
