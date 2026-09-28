<?php

namespace App\Services;

use App\Models\Contacto;
use App\Models\Sede;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Genera la plantilla de carga y las exportaciones de contactos en Excel.
 */
class ExcelContactos
{
    public static function columnas(string $tipo): array
    {
        $propias = $tipo === Contacto::PADRE
            ? ['estudiante' => 'Estudiante', 'grado_seccion' => 'Grado y sección']
            : ['area' => 'Curso o área'];

        return ['nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'dpi' => 'DPI', 'correo' => 'Correo',
            'telefono' => 'Teléfono', 'sede' => 'Sede'] + $propias + ['grupos' => 'Grupos'];
    }

    public static function plantilla(string $tipo): StreamedResponse
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($tipo === Contacto::PADRE ? 'Padres de familia' : 'Catedráticos');
        $columnas = self::columnas($tipo);
        self::encabezados($hoja, $columnas);

        $sedes = Sede::activas()->orderBy('nombre')->pluck('nombre');
        $ejemplos = $tipo === Contacto::PADRE
            ? [
                ['María José', 'García López', '2584736910207', 'mariajose.garcia@correo.com', '5874-3210', $sedes[0] ?? '', 'Ana Lucía Pérez García', '3.º Básico A', 'Padres 3.º Básico'],
                ['Carlos Enrique', 'Pérez Ramírez', '', 'carlos.perez@correo.com', '4478-5632', $sedes[1] ?? '', 'Diego Pérez Morales', '1.º Primaria B', ''],
            ]
            : [
                ['Luis Fernando', 'Morales Cruz', '2145678901601', 'luis.morales@correo.com', '4567-8123', $sedes[0] ?? '', 'Matemática', 'Claustro básico'],
                ['Ana Beatriz', 'Chen Rodríguez', '', 'ana.chen@correo.com', '3012-3456', $sedes[2] ?? '', 'Ciencias Naturales', ''],
            ];
        self::filas($hoja, $ejemplos, 2);

        // Lista desplegable de sedes en la columna "Sede" (filas 2 a 1000)
        $colSede = self::letra(array_search('sede', array_keys($columnas)) + 1);
        $validacion = $hoja->getCell("{$colSede}2")->getDataValidation();
        $validacion->setType(DataValidation::TYPE_LIST)->setAllowBlank(false)->setShowDropDown(true)
            ->setShowErrorMessage(true)->setErrorTitle('Sede no válida')->setError('Elija una sede de la lista.')
            ->setFormula1('"'.$sedes->join(',').'"');
        $hoja->setDataValidation("{$colSede}2:{$colSede}1000", $validacion);

        // Instrucciones
        $info = $libro->createSheet();
        $info->setTitle('Instrucciones');
        $lineas = [
            ['Cómo llenar la plantilla'],
            [''],
            ['• Una persona por fila. No cambie los títulos de la primera fila.'],
            ['• Obligatorios: Nombres, Apellidos y Sede.'],
            ['• Sede: '.$sedes->join(', ').' (puede escribirla sin tilde).'],
            ['• Correo: necesario para recibir invitaciones. No se permite el mismo correo dos veces.'],
            ['• DPI: 13 dígitos, sin espacios ni guiones (opcional).'],
            ['• Teléfono: 8 dígitos, por ejemplo 5874-3210 (opcional).'],
            ['• Grupos: nombres separados por coma, por ejemplo "Padres 3.º Básico, Comité de padres". Si el grupo no existe, se crea.'],
            ['• Si una persona ya existe (mismo correo o DPI), sus datos se actualizan con lo que venga lleno en el archivo.'],
            ['• Borre las filas de ejemplo antes de subir el archivo.'],
        ];
        foreach ($lineas as $i => [$texto]) {
            $info->setCellValue('A'.($i + 1), $texto);
        }
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $info->getColumnDimension('A')->setWidth(110);

        $libro->setActiveSheetIndex(0);
        $nombre = $tipo === Contacto::PADRE ? 'plantilla_padres_de_familia.xlsx' : 'plantilla_catedraticos.xlsx';

        return self::descargar($libro, $nombre);
    }

    /** @param  Collection<int, Contacto>  $contactos */
    public static function exportar(string $tipo, Collection $contactos): StreamedResponse
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($tipo === Contacto::PADRE ? 'Padres de familia' : 'Catedráticos');
        $columnas = self::columnas($tipo) + ['acepta_correos' => 'Acepta correos'];
        self::encabezados($hoja, $columnas);

        $filas = $contactos->map(function (Contacto $c) use ($columnas) {
            return collect(array_keys($columnas))->map(fn ($campo) => match ($campo) {
                'sede' => $c->sede->nombre,
                'grupos' => $c->grupos->pluck('nombre')->join(', '),
                'acepta_correos' => $c->acepta_correos ? 'Sí' : 'No',
                default => $c->{$campo},
            })->all();
        })->all();
        self::filas($hoja, $filas, 2);
        $hoja->setAutoFilter($hoja->calculateWorksheetDimension());

        $prefijo = $tipo === Contacto::PADRE ? 'padres_de_familia' : 'catedraticos';

        return self::descargar($libro, $prefijo.'_'.now()->format('Y-m-d').'.xlsx');
    }

    private static function encabezados(Worksheet $hoja, array $columnas): void
    {
        $i = 1;
        foreach ($columnas as $campo => $titulo) {
            $letra = self::letra($i++);
            $hoja->setCellValue("{$letra}1", $titulo);
            $hoja->getColumnDimension($letra)->setWidth(in_array($campo, ['correo', 'estudiante', 'grupos']) ? 32 : 20);
            // DPI y teléfono como texto para que Excel no los convierta en números
            if (in_array($campo, ['dpi', 'telefono'])) {
                $hoja->getStyle("{$letra}2:{$letra}5000")->getNumberFormat()->setFormatCode('@');
            }
        }
        $rango = 'A1:'.self::letra(count($columnas)).'1';
        $hoja->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
        $hoja->freezePane('A2');
    }

    private static function filas(Worksheet $hoja, array $filas, int $desde): void
    {
        foreach ($filas as $r => $fila) {
            foreach (array_values($fila) as $c => $valor) {
                $hoja->setCellValueExplicit(self::letra($c + 1).($desde + $r), (string) $valor, DataType::TYPE_STRING);
            }
        }
    }

    private static function letra(int $n): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
    }

    private static function descargar(Spreadsheet $libro, string $nombre): StreamedResponse
    {
        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
