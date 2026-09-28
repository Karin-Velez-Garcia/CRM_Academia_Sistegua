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
            ['nombre' => 'Invitación a capacitación', 'tipo_evento' => Evento::CAPACITACION],
            [
                'asunto' => 'Capacitación: {titulo} — {fecha}',
                'mensaje' => "Estimado(a) {nombre}:\n\nReciba un cordial saludo de {academia}. Le invitamos a la capacitación \"{titulo}\", que se realizará el {fecha}, de {hora}, en {lugar}.\n\nAl finalizar se entrega constancia de participación.\n\nLe agradecemos confirmar su asistencia con los botones de este correo.",
                'predeterminada' => true,
            ]
        );

        Plantilla::firstOrCreate(
            ['nombre' => 'Invitación a taller práctico', 'tipo_evento' => Evento::CAPACITACION],
            [
                'asunto' => 'Taller práctico: {titulo} — {fecha}',
                'mensaje' => "Estimado(a) {nombre}:\n\n{academia}, sede {sede}, le invita al taller práctico \"{titulo}\", el {fecha}, de {hora}, en {lugar}.\n\nEl taller incluye demostración en sitio con producto. Se recomienda asistir con equipo de protección personal.\n\nPor favor confirme su participación con los botones de este correo.",
                'predeterminada' => false,
            ]
        );
    }
}
