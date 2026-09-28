<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Services\InvitacionesEvento;
use Illuminate\Console\Command;

/**
 * Envía el recordatorio automático de los eventos próximos (por defecto, el día anterior a las 07:00).
 * La tarea programada lo ejecuta cada 15 minutos; cada evento se recuerda una sola vez.
 */
class EnviarRecordatorios extends Command
{
    protected $signature = 'recordatorios:enviar {--simular : Solo muestra qué se enviaría, sin enviar nada}';

    protected $description = 'Envía el recordatorio automático de reuniones y capacitaciones próximas';

    public function handle(): int
    {
        $eventos = Evento::conRecordatorioPendiente();

        if ($eventos->isEmpty()) {
            $this->info('No hay recordatorios pendientes.');

            return self::SUCCESS;
        }

        foreach ($eventos as $evento) {
            $etiqueta = "{$evento->titulo} ({$evento->inicio->format('d/m/Y H:i')}, sede {$evento->sede->nombre})";

            if ($this->option('simular')) {
                $this->line("Se enviaría: {$etiqueta}");

                continue;
            }

            [$confirmados, $sinRespuesta] = (new InvitacionesEvento($evento))->recordatorioAutomatico();
            $this->info("Enviado: {$etiqueta} → {$confirmados} confirmados, {$sinRespuesta} sin respuesta");
        }

        return self::SUCCESS;
    }
}
