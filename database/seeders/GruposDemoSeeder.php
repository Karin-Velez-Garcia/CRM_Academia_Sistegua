<?php

namespace Database\Seeders;

use App\Models\Contacto;
use App\Models\Grupo;
use App\Models\Sede;
use Illuminate\Database\Seeder;

/**
 * Grupos de demostración por sede, segmentados por el oficio del cliente,
 * más un grupo general de todas las sedes.
 */
class GruposDemoSeeder extends Seeder
{
    /** Grupo => oficios que lo componen. */
    private array $segmentos = [
        'Instaladores' => ['Instalador de tabla yeso', 'Instalador de cielo falso', 'Instalador independiente'],
        'Contratistas y obra' => ['Contratista', 'Maestro de obra', 'Supervisor de obra', 'Residente de obra', 'Albañil', 'Carpintero'],
        'Ferreterías aliadas' => ['Vendedor de ferretería', 'Propietario de ferretería'],
        'Arquitectos e ingenieros' => ['Arquitecto', 'Ingeniero civil'],
    ];

    public function run(): void
    {
        foreach (Sede::all() as $sede) {
            foreach ($this->segmentos as $nombre => $oficios) {
                $grupo = Grupo::firstOrCreate(
                    ['nombre' => "{$nombre} - {$sede->nombre}", 'sede_id' => $sede->id],
                    ['tipo' => Contacto::CLIENTE, 'descripcion' => "{$nombre} registrados en la sede {$sede->nombre}."]
                );

                $grupo->contactos()->sync(
                    Contacto::where('sede_id', $sede->id)->whereIn('oficio', $oficios)->pluck('id')
                );
            }
        }

        $certificados = Grupo::firstOrCreate(
            ['nombre' => 'Instaladores certificados', 'sede_id' => null],
            ['tipo' => Contacto::CLIENTE, 'descripcion' => 'Clientes que ya completaron la ruta de certificación, de todas las sedes.']
        );
        $certificados->contactos()->sync(
            Contacto::whereIn('oficio', ['Instalador de tabla yeso', 'Instalador de cielo falso'])
                ->inRandomOrder()->limit(45)->pluck('id')
        );
    }
}
