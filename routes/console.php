<?php

use Illuminate\Support\Facades\Schedule;

// Por defecto los recordatorios se envían con el botón "Enviar recordatorio" de cada evento.
// Solo si el sistema se instala en un servidor con tarea programada (php artisan schedule:run cada minuto)
// y se pone RECORDATORIO_AUTOMATICO=true en el .env, se envían también de forma automática el día anterior.
if (config('colegio.recordatorio.automatico')) {
    Schedule::command('recordatorios:enviar')
        ->everyFifteenMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/recordatorios.log'));
}
