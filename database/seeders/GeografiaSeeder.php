<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Municipio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class GeografiaSeeder extends Seeder
{
    /**
     * Carga los 22 departamentos y 340 municipios de Guatemala (códigos INE).
     */
    public function run(): void
    {
        $datos = File::json(database_path('data/guatemala.json'));

        foreach ($datos as $dep) {
            $departamento = Departamento::updateOrCreate(
                ['codigo' => $dep['codigo']],
                ['nombre' => $dep['nombre'], 'region' => $dep['region']]
            );

            foreach ($dep['municipios'] as $mun) {
                Municipio::updateOrCreate(
                    ['codigo' => $mun['codigo']],
                    ['departamento_id' => $departamento->id, 'nombre' => $mun['nombre']]
                );
            }
        }
    }
}
