<?php

// Datos institucionales que se muestran en la interfaz, correos y documentos.
return [
    'nombre' => env('APP_NAME', 'Academia Sistegua'),
    'siglas' => 'AS',
    'lema' => 'Capacitación técnica en sistemas de construcción liviana',
    'sedes' => ['Ciudad de Guatemala', 'Quetzaltenango', 'Chiquimula'],

    // Recordatorio automático antes de cada capacitación (se puede apagar por evento)
    'recordatorio' => [
        // Por defecto el recordatorio se envía con el botón de la ficha del evento. Activar el envío
        // automático solo en un servidor con la tarea programada (php artisan schedule:run cada minuto).
        'automatico' => (bool) env('RECORDATORIO_AUTOMATICO', false),
        'dias_antes' => (int) env('RECORDATORIO_DIAS_ANTES', 1),
        'hora' => env('RECORDATORIO_HORA', '07:00'),
    ],
];
