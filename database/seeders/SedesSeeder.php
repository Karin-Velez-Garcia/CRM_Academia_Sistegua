<?php

namespace Database\Seeders;

use App\Models\Municipio;
use App\Models\Sede;
use Illuminate\Database\Seeder;

class SedesSeeder extends Seeder
{
    /**
     * Sedes del Colegio Esteca PC (municipio por código INE).
     */
    public function run(): void
    {
        foreach ([
            'Sanarate' => '0207', // Sanarate, El Progreso
            'Salamá' => '1501',   // Salamá, Baja Verapaz
            'Cobán' => '1601',    // Cobán, Alta Verapaz
        ] as $nombre => $codigo) {
            Sede::firstOrCreate(
                ['nombre' => $nombre],
                ['municipio_id' => Municipio::where('codigo', $codigo)->value('id')]
            );
        }
    }
}
