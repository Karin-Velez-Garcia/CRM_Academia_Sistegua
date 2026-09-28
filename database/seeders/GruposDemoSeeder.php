<?php

namespace Database\Seeders;

use App\Models\Contacto;
use App\Models\Grupo;
use App\Models\Sede;
use Illuminate\Database\Seeder;

/**
 * Grupos de demostración por sede (padres por nivel, catedráticos) y dos grupos
 * generales de todas las sedes, con sus miembros ya asignados.
 */
class GruposDemoSeeder extends Seeder
{
    private array $niveles = [
        'Preprimaria' => ['Párvulos 1', 'Párvulos 2', 'Párvulos 3'],
        'Primaria' => ['Primero Primaria', 'Segundo Primaria', 'Tercero Primaria', 'Cuarto Primaria', 'Quinto Primaria', 'Sexto Primaria'],
        'Básico' => ['Primero Básico', 'Segundo Básico', 'Tercero Básico'],
    ];

    public function run(): void
    {
        foreach (Sede::all() as $sede) {
            foreach (array_keys($this->niveles) as $nivel) {
                $grupo = Grupo::firstOrCreate(
                    ['nombre' => "Padres de {$nivel} - {$sede->nombre}", 'sede_id' => $sede->id],
                    ['tipo' => Contacto::PADRE, 'descripcion' => "Padres de familia de {$nivel} en la sede {$sede->nombre}."]
                );

                $grados = $this->niveles[$nivel];
                $padres = Contacto::tipo(Contacto::PADRE)
                    ->where('sede_id', $sede->id)
                    ->where(function ($q) use ($grados) {
                        foreach ($grados as $grado) {
                            $q->orWhere('grado_seccion', 'like', "{$grado}%");
                        }
                    })
                    ->pluck('id');

                $grupo->contactos()->sync($padres);
            }

            $catedraticos = Grupo::firstOrCreate(
                ['nombre' => "Catedráticos - {$sede->nombre}", 'sede_id' => $sede->id],
                ['tipo' => Contacto::CATEDRATICO, 'descripcion' => "Cuerpo docente de la sede {$sede->nombre}."]
            );
            $catedraticos->contactos()->sync(
                Contacto::tipo(Contacto::CATEDRATICO)->where('sede_id', $sede->id)->pluck('id')
            );
        }

        // Grupos generales, de todas las sedes
        $juntaDirectiva = Grupo::firstOrCreate(
            ['nombre' => 'Junta Directiva de Padres', 'sede_id' => null],
            ['tipo' => Contacto::PADRE, 'descripcion' => 'Representantes de padres de familia de las tres sedes.']
        );
        $juntaDirectiva->contactos()->sync(
            Contacto::tipo(Contacto::PADRE)->inRandomOrder()->limit(12)->pluck('id')
        );

        $cuerpoDocenteGeneral = Grupo::firstOrCreate(
            ['nombre' => 'Cuerpo Docente General', 'sede_id' => null],
            ['tipo' => Contacto::CATEDRATICO, 'descripcion' => 'Todos los catedráticos, de las tres sedes.']
        );
        $cuerpoDocenteGeneral->contactos()->sync(
            Contacto::tipo(Contacto::CATEDRATICO)->pluck('id')
        );
    }
}
