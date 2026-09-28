<?php

namespace Database\Seeders;

use App\Models\Evento;
use App\Models\Plantilla;
use Illuminate\Database\Seeder;

class PlantillasSeeder extends Seeder
{
    public function run(): void
    {
        Plantilla::firstOrCreate(
            ['nombre' => 'Invitación a reunión de padres', 'tipo_evento' => Evento::REUNION],
            [
                'asunto' => 'Invitación: {titulo} — {fecha}',
                'mensaje' => "Estimado(a) {nombre}:\n\nReciba un cordial saludo del {colegio}. Le invitamos a la reunión \"{titulo}\", que se realizará el {fecha}, de {hora}, en {lugar}.\n\nSu participación es muy importante para acompañar el proceso educativo de {estudiante}.\n\nLe agradecemos confirmar su asistencia con los botones de este correo.",
                'predeterminada' => true,
            ]
        );

        Plantilla::firstOrCreate(
            ['nombre' => 'Convocatoria a capacitación docente', 'tipo_evento' => Evento::CAPACITACION],
            [
                'asunto' => 'Capacitación: {titulo} — {fecha}',
                'mensaje' => "Estimado(a) {nombre}:\n\nLa dirección del {colegio}, sede {sede}, le convoca a la capacitación \"{titulo}\", el {fecha}, de {hora}, en {lugar}.\n\nPor favor confirme su participación con los botones de este correo.",
                'predeterminada' => true,
            ]
        );
    }
}
