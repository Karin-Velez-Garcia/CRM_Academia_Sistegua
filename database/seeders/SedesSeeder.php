<?php

namespace Database\Seeders;

use App\Models\Municipio;
use App\Models\Sede;
use Illuminate\Database\Seeder;

class SedesSeeder extends Seeder
{
    /**
     * Sedes de la academia (municipio por código INE).
     * Ajustar a las sucursales reales de la empresa antes de producción.
     */
    public function run(): void
    {
        foreach ([
            'Ciudad de Guatemala' => '0101', // Guatemala, Guatemala
            'Quetzaltenango' => '0901',      // Quetzaltenango, Quetzaltenango
            'Chiquimula' => '2001',          // Chiquimula, Chiquimula
        ] as $nombre => $codigo) {
            Sede::firstOrCreate(
                ['nombre' => $nombre],
                ['municipio_id' => Municipio::where('codigo', $codigo)->value('id')]
            );
        }
    }
}
