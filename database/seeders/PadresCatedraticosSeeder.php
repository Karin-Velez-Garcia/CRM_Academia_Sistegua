<?php

namespace Database\Seeders;

use App\Models\Contacto;
use App\Models\Sede;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de demostración: padres de familia y catedráticos, con nombres y apellidos
 * típicos de cada región donde el colegio tiene sede (mezcla de apellidos hispanos
 * comunes en Guatemala con apellidos mayas propios de cada departamento).
 */
class PadresCatedraticosSeeder extends Seeder
{
    /** Cuántos padres crear por sede. */
    public const PADRES_POR_SEDE = [
        'Sanarate' => 160,
        'Salamá' => 130,
        'Cobán' => 120,
    ];

    /** Cuántos catedráticos crear por sede (15 en total). */
    public const CATEDRATICOS_POR_SEDE = [
        'Sanarate' => 5,
        'Salamá' => 5,
        'Cobán' => 5,
    ];

    private array $nombresM = [
        'José', 'Juan', 'Carlos', 'Luis', 'Miguel', 'Francisco', 'Manuel', 'Pedro', 'Antonio', 'Roberto',
        'Mario', 'Edwin', 'Byron', 'Estuardo', 'Marvin', 'Fredy', 'Wilmer', 'Otoniel', 'Erick', 'Cristian',
        'Rony', 'Selvin', 'Elmer', 'Haroldo', 'Amílcar', 'Baudilio', 'Rigoberto', 'Herbert', 'Nery', 'Gerson',
        'Alejandro', 'Fernando', 'Ricardo', 'Oscar', 'Julio', 'Ronaldo', 'Vinicio', 'Abner', 'Sergio', 'Danilo',
    ];

    private array $nombresF = [
        'María', 'Ana', 'Rosa', 'Carmen', 'Elena', 'Marta', 'Silvia', 'Gloria', 'Diana', 'Yolanda',
        'Patricia', 'Claudia', 'Karen', 'Lesbia', 'Aracely', 'Floridalma', 'Vilma', 'Miriam', 'Delmy', 'Norma',
        'Ingrid', 'Mirna', 'Sandra', 'Blanca', 'Lidia', 'Zoila', 'Herlinda', 'Consuelo', 'Amparo', 'Alba',
        'Dora', 'Estela', 'Lucía', 'Beatriz', 'Cecilia', 'Rosario', 'Analy', 'Wendy', 'Evelyn', 'Heidy',
    ];

    /** Segundo nombre opcional, típico en actas guatemaltecas. */
    private array $segundosNombres = ['José', 'María', 'Carlos', 'Elena', 'Antonio', 'Isabel', 'Alberto', 'Concepción'];

    /** Apellidos hispanos comunes en toda Guatemala. */
    private array $apellidosComunes = [
        'García', 'López', 'González', 'Rodríguez', 'Hernández', 'Martínez', 'Pérez', 'Sánchez', 'Ramírez', 'Flores',
        'Morales', 'Gómez', 'Cruz', 'Reyes', 'Jiménez', 'Torres', 'Rivera', 'Gutiérrez', 'Mendoza', 'Vásquez',
        'Castillo', 'Chávez', 'Romero', 'Herrera', 'Medina', 'Aguilar', 'Estrada', 'Ortiz', 'Guzmán', 'Contreras',
        'Solís', 'Barrios', 'Cabrera', 'Recinos', 'Monterroso', 'Villatoro', 'Cordón', 'Corado', 'Bautista', 'Cerna',
        'Gudiel', 'Girón', 'Marroquín', 'Escobar', 'Pineda', 'Rosales', 'Samayoa', 'Godínez', 'Zamora', 'Argueta',
    ];

    /** Apellidos achí, propios de Baja Verapaz (Salamá). */
    private array $apellidosAchi = [
        'Xitumul', 'Sic', 'Cahuec', 'Ical', 'Chen', 'Cuxum', 'Sis', 'Iboy', 'Tahuico', 'Cuxil', 'Sactic', 'Xoyón',
    ];

    /** Apellidos q'eqchi', propios de Alta Verapaz (Cobán). */
    private array $apellidosQeqchi = [
        'Caal', 'Cuc', 'Coy', 'Xol', 'Tzul', 'Ba', 'Choc', 'Pop', 'Bin', 'Cucul', 'Tiul', 'Yat',
        'Chub', 'Maaz', 'Bolom', 'Sub', 'Icó', 'Pacay', 'Coc', 'Che', 'Quej', 'Rax', 'Cho',
    ];

    private array $nombresNinoM = [
        'Juan', 'Diego', 'Kevin', 'Josué', 'André', 'Ángel', 'Brandon', 'Emerson', 'Jonathan', 'Bryan',
        'Alexander', 'Dylan', 'Mateo', 'Sebastián', 'Santiago', 'Estuardo', 'Marlon', 'Axel',
    ];

    private array $nombresNinaF = [
        'María', 'Ashley', 'Fernanda', 'Génesis', 'Valeria', 'Kimberly', 'Andrea', 'Alison', 'Melany', 'Dulce',
        'Sofía', 'Camila', 'Nicole', 'Xiomara', 'Yesenia', 'Britany', 'Paola', 'Abigail',
    ];

    private array $grados = [
        'Párvulos 1', 'Párvulos 2', 'Párvulos 3',
        'Primero Primaria', 'Segundo Primaria', 'Tercero Primaria',
        'Cuarto Primaria', 'Quinto Primaria', 'Sexto Primaria',
        'Primero Básico', 'Segundo Básico', 'Tercero Básico',
    ];

    private array $areasCatedratico = [
        'Matemática', 'Comunicación y Lenguaje L1', 'Ciencias Naturales', 'Ciencias Sociales',
        'Idioma Extranjero (Inglés)', 'Educación Física', 'Expresión Artística', 'Formación Ciudadana',
        'Productividad y Desarrollo', 'Tecnología del Aprendizaje', 'Emprendimiento para la Productividad',
        'Física y Química (Básico)', 'Contabilidad (Básico)', 'Orientación', 'Educación Musical',
    ];

    /** Peso de apellido regional (0 a 1) según la sede, el resto usa apellidos comunes. */
    private array $pesoRegional = [
        'Sanarate' => 0.08,
        'Salamá' => 0.45,
        'Cobán' => 0.60,
    ];

    private array $dpiUsados = [];
    private array $correosUsados = [];

    public function run(): void
    {
        $sedes = Sede::pluck('id', 'nombre');

        foreach (self::PADRES_POR_SEDE as $nombreSede => $cantidad) {
            for ($i = 0; $i < $cantidad; $i++) {
                $this->crearPadre($sedes[$nombreSede], $nombreSede);
            }
        }

        $areasDisponibles = $this->areasCatedratico;
        foreach (self::CATEDRATICOS_POR_SEDE as $nombreSede => $cantidad) {
            for ($i = 0; $i < $cantidad; $i++) {
                $area = $areasDisponibles[array_rand($areasDisponibles)];
                $this->crearCatedratico($sedes[$nombreSede], $nombreSede, $area);
            }
        }
    }

    private function crearPadre(int $sedeId, string $nombreSede): void
    {
        $esHombre = random_int(0, 1) === 1;
        $nombres = $this->generarNombre($esHombre);
        $apellidos = $this->generarApellidos($nombreSede);
        $correo = random_int(1, 100) <= 88 ? $this->generarCorreo($nombres, $apellidos, Contacto::PADRE) : null;

        $grado = $this->grados[array_rand($this->grados)];
        $seccion = ['A', 'B', 'C'][array_rand(['A', 'B', 'C'])];

        Contacto::create([
            'tipo' => Contacto::PADRE,
            'sede_id' => $sedeId,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'dpi' => $this->generarDpi(Contacto::PADRE),
            'correo' => $correo,
            'telefono' => $this->generarTelefono(),
            'estudiante' => $this->generarNombreEstudiante($apellidos),
            'grado_seccion' => "{$grado} \"{$seccion}\"",
            'acepta_correos' => $correo && random_int(1, 100) <= 96,
        ]);
    }

    private function crearCatedratico(int $sedeId, string $nombreSede, string $area): void
    {
        $esHombre = random_int(0, 1) === 1;
        $nombres = $this->generarNombre($esHombre);
        $apellidos = $this->generarApellidos($nombreSede);
        $correo = $this->generarCorreo($nombres, $apellidos, Contacto::CATEDRATICO, institucional: true);

        Contacto::create([
            'tipo' => Contacto::CATEDRATICO,
            'sede_id' => $sedeId,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'dpi' => $this->generarDpi(Contacto::CATEDRATICO),
            'correo' => $correo,
            'telefono' => $this->generarTelefono(),
            'area' => $area,
            'acepta_correos' => true,
        ]);
    }

    private function generarNombre(bool $esHombre): string
    {
        $pool = $esHombre ? $this->nombresM : $this->nombresF;
        $nombre = $pool[array_rand($pool)];

        if (random_int(1, 100) <= 35) {
            $segundo = $this->segundosNombres[array_rand($this->segundosNombres)];
            if ($segundo !== $nombre) {
                $nombre .= " {$segundo}";
            }
        }

        return $nombre;
    }

    private function generarApellidos(string $sede): string
    {
        $peso = $this->pesoRegional[$sede] ?? 0;
        $regional = match ($sede) {
            'Salamá' => $this->apellidosAchi,
            'Cobán' => $this->apellidosQeqchi,
            default => [],
        };

        $paterno = $this->elegirApellido($regional, $peso);
        $materno = $this->elegirApellido($regional, $peso);

        return "{$paterno} {$materno}";
    }

    private function elegirApellido(array $regional, float $peso): string
    {
        if ($regional && random_int(1, 100) <= $peso * 100) {
            return $regional[array_rand($regional)];
        }

        return $this->apellidosComunes[array_rand($this->apellidosComunes)];
    }

    private function generarNombreEstudiante(string $apellidosPadre): string
    {
        $esHombre = random_int(0, 1) === 1;
        $pool = $esHombre ? $this->nombresNinoM : $this->nombresNinaF;

        return $pool[array_rand($pool)].' '.$apellidosPadre;
    }

    private function generarDpi(string $tipo): string
    {
        do {
            $dpi = (string) random_int(1000000000000, 9999999999999);
        } while (in_array("{$tipo}:{$dpi}", $this->dpiUsados, true));

        $this->dpiUsados[] = "{$tipo}:{$dpi}";

        return $dpi;
    }

    private function generarCorreo(string $nombres, string $apellidos, string $tipo, bool $institucional = false): string
    {
        $base = Str::of($nombres.'.'.explode(' ', $apellidos)[0])->ascii()->lower()->replace(' ', '')->value();
        $dominio = $institucional ? 'colegioesteca.edu.gt' : ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com'][array_rand(['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com'])];

        $intento = 0;
        do {
            $sufijo = $intento === 0 ? '' : (string) random_int(1, 999);
            $correo = "{$base}{$sufijo}@{$dominio}";
            $clave = "{$tipo}:{$correo}";
            $intento++;
        } while (in_array($clave, $this->correosUsados, true));

        $this->correosUsados[] = $clave;

        return $correo;
    }

    private function generarTelefono(): string
    {
        $inicio = [3, 4, 5, 6, 7][array_rand([3, 4, 5, 6, 7])];

        return $inicio.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }
}
