<?php

namespace Database\Seeders;

use App\Models\Contacto;
use App\Models\Sede;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de demostración: clientes que asisten a las capacitaciones de la academia
 * (instaladores, contratistas, ferreterías, arquitectos), con nombres y apellidos
 * típicos de cada región donde hay sede.
 */
class ClientesSeeder extends Seeder
{
    public const CLIENTES_POR_SEDE = [
        'Ciudad de Guatemala' => 180,
        'Quetzaltenango' => 120,
        'Chiquimula' => 100,
    ];

    private array $nombresM = [
        'José', 'Juan', 'Carlos', 'Luis', 'Miguel', 'Francisco', 'Manuel', 'Pedro', 'Antonio', 'Roberto',
        'Mario', 'Edwin', 'Byron', 'Estuardo', 'Marvin', 'Fredy', 'Wilmer', 'Otoniel', 'Erick', 'Cristian',
        'Rony', 'Selvin', 'Elmer', 'Haroldo', 'Amílcar', 'Rigoberto', 'Herbert', 'Nery', 'Gerson', 'Walter',
        'Alejandro', 'Fernando', 'Ricardo', 'Óscar', 'Julio', 'Vinicio', 'Abner', 'Sergio', 'Danilo', 'Jorge',
    ];

    private array $nombresF = [
        'María', 'Ana', 'Rosa', 'Carmen', 'Elena', 'Marta', 'Silvia', 'Gloria', 'Diana', 'Yolanda',
        'Patricia', 'Claudia', 'Karen', 'Lesbia', 'Aracely', 'Vilma', 'Miriam', 'Delmy', 'Norma', 'Ingrid',
        'Mirna', 'Sandra', 'Blanca', 'Lidia', 'Herlinda', 'Dora', 'Estela', 'Lucía', 'Beatriz', 'Cecilia',
        'Wendy', 'Evelyn', 'Heidy', 'Astrid', 'Jackeline', 'Mayra', 'Sucely', 'Gabriela', 'Andrea', 'Paola',
    ];

    private array $segundosNombres = ['José', 'María', 'Carlos', 'Elena', 'Antonio', 'Isabel', 'Alberto', 'Fernanda'];

    private array $apellidosComunes = [
        'García', 'López', 'González', 'Rodríguez', 'Hernández', 'Martínez', 'Pérez', 'Sánchez', 'Ramírez', 'Flores',
        'Morales', 'Gómez', 'Cruz', 'Reyes', 'Jiménez', 'Torres', 'Rivera', 'Gutiérrez', 'Mendoza', 'Vásquez',
        'Castillo', 'Chávez', 'Romero', 'Herrera', 'Medina', 'Aguilar', 'Estrada', 'Ortiz', 'Guzmán', 'Contreras',
        'Solís', 'Barrios', 'Cabrera', 'Recinos', 'Monterroso', 'Villatoro', 'Marroquín', 'Escobar', 'Pineda', 'Rosales',
        'Samayoa', 'Godínez', 'Zamora', 'Argueta', 'Girón', 'Orozco', 'Alvarado', 'Ovalle', 'Cifuentes', 'Juárez',
    ];

    /** Apellidos k'iche', frecuentes en el altiplano (Quetzaltenango). */
    private array $apellidosKiche = [
        'Tzoc', 'Chuc', 'Sam', 'Coyoy', 'Yac', 'Tzunún', 'Ixcot', 'Cotom', 'Saquic', 'Vicente',
        'Batz', 'Tuy', 'Chan', 'Ajanel', 'Calel', 'Chox',
    ];

    private array $oficios = [
        'Instalador de tabla yeso', 'Instalador de cielo falso', 'Contratista', 'Maestro de obra',
        'Arquitecto', 'Ingeniero civil', 'Vendedor de ferretería', 'Propietario de ferretería',
        'Carpintero', 'Albañil', 'Supervisor de obra', 'Residente de obra', 'Instalador independiente',
    ];

    private array $prefijosEmpresa = [
        'Constructora', 'Ferretería', 'Distribuidora', 'Multiservicios', 'Proyectos', 'Acabados',
        'Servicios de Construcción', 'Depósito', 'Grupo Constructor',
    ];

    private array $sufijosEmpresa = [
        'El Progreso', 'La Económica', 'del Valle', 'San José', 'Los Pinos', 'Santa Fe', 'El Constructor',
        'La Bendición', 'Xelajú', 'Del Sur', 'Centroamericana', 'La Unión', 'El Roble', 'Monte María',
    ];

    private array $dpiUsados = [];
    private array $correosUsados = [];

    public function run(): void
    {
        $sedes = Sede::pluck('id', 'nombre');

        foreach (self::CLIENTES_POR_SEDE as $nombreSede => $cantidad) {
            for ($i = 0; $i < $cantidad; $i++) {
                $this->crearCliente($sedes[$nombreSede], $nombreSede);
            }
        }
    }

    private function crearCliente(int $sedeId, string $nombreSede): void
    {
        $esHombre = random_int(1, 100) <= 78; // el rubro es mayoritariamente masculino
        $nombres = $this->generarNombre($esHombre);
        $apellidos = $this->generarApellidos($nombreSede);
        $correo = random_int(1, 100) <= 85 ? $this->generarCorreo($nombres, $apellidos) : null;
        $oficio = $this->oficios[array_rand($this->oficios)];

        Contacto::create([
            'tipo' => Contacto::CLIENTE,
            'sede_id' => $sedeId,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'dpi' => $this->generarDpi(),
            'correo' => $correo,
            'telefono' => $this->generarTelefono(),
            'empresa' => $this->generarEmpresa($oficio),
            'oficio' => $oficio,
            'acepta_correos' => $correo && random_int(1, 100) <= 95,
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
        $peso = $sede === 'Quetzaltenango' ? 0.35 : 0.05;

        return $this->elegirApellido($peso).' '.$this->elegirApellido($peso);
    }

    private function elegirApellido(float $peso): string
    {
        if (random_int(1, 100) <= $peso * 100) {
            return $this->apellidosKiche[array_rand($this->apellidosKiche)];
        }

        return $this->apellidosComunes[array_rand($this->apellidosComunes)];
    }

    /** Los instaladores independientes muchas veces no tienen empresa registrada. */
    private function generarEmpresa(string $oficio): ?string
    {
        if (str_contains($oficio, 'independiente') && random_int(1, 100) <= 70) {
            return null;
        }

        return $this->prefijosEmpresa[array_rand($this->prefijosEmpresa)].' '
            .$this->sufijosEmpresa[array_rand($this->sufijosEmpresa)];
    }

    private function generarDpi(): string
    {
        do {
            $dpi = (string) random_int(1000000000000, 9999999999999);
        } while (in_array($dpi, $this->dpiUsados, true));

        $this->dpiUsados[] = $dpi;

        return $dpi;
    }

    private function generarCorreo(string $nombres, string $apellidos): string
    {
        $base = Str::of($nombres.'.'.explode(' ', $apellidos)[0])->ascii()->lower()->replace(' ', '')->value();
        $dominios = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com'];
        $dominio = $dominios[array_rand($dominios)];

        $intento = 0;
        do {
            $sufijo = $intento === 0 ? '' : (string) random_int(1, 999);
            $correo = "{$base}{$sufijo}@{$dominio}";
            $intento++;
        } while (in_array($correo, $this->correosUsados, true));

        $this->correosUsados[] = $correo;

        return $correo;
    }

    private function generarTelefono(): string
    {
        $inicios = [3, 4, 5, 6, 7];
        $inicio = $inicios[array_rand($inicios)];

        return $inicio.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }
}
