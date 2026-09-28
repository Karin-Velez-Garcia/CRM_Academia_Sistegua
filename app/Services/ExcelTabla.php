<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga una tabla simple en Excel: título, encabezados con el color del sistema y filas.
 */
class ExcelTabla
{
    /**
     * @param  list<string>  $encabezados
     * @param  iterable<array>  $filas  Los valores numéricos se guardan como números; el resto como texto.
     */
    public static function descargar(string $titulo, array $encabezados, iterable $filas, string $archivo, ?string $subtitulo = null): StreamedResponse
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $titulo), 0, 31));

        $hoja->setCellValue('A1', $titulo);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->setCellValue('A2', $subtitulo ?? config('colegio.nombre').' · Generado el '.now()->format('d/m/Y H:i'));
        $hoja->getStyle('A2')->getFont()->getColor()->setRGB('78829D');

        $filaEnc = 4;
        foreach ($encabezados as $i => $enc) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$filaEnc, $enc);
        }
        $ultima = Coordinate::stringFromColumnIndex(count($encabezados));
        $rango = "A{$filaEnc}:{$ultima}{$filaEnc}";
        $hoja->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');

        $r = $filaEnc + 1;
        foreach ($filas as $fila) {
            foreach (array_values($fila) as $c => $valor) {
                $celda = Coordinate::stringFromColumnIndex($c + 1).$r;
                if (is_int($valor) || is_float($valor)) {
                    $hoja->setCellValue($celda, $valor);
                } else {
                    $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
                }
            }
            $r++;
        }

        foreach (range(1, count($encabezados)) as $c) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
        if ($r > $filaEnc + 1) {
            $hoja->setAutoFilter("A{$filaEnc}:{$ultima}".($r - 1));
        }
        $hoja->freezePane('A'.($filaEnc + 1));

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $archivo, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
