<?php

namespace Database\Seeders;

use App\Models\PlantillaCertificado;
use Illuminate\Database\Seeder;

class CertificadosSeeder extends Seeder
{
    public function run(): void
    {
        PlantillaCertificado::firstOrCreate(
            ['nombre' => 'Constancia de participación'],
            [
                'orientacion' => PlantillaCertificado::HORIZONTAL,
                'elementos' => PlantillaCertificado::disenoInicial(),
                'predeterminada' => true,
            ]
        );
    }
}
