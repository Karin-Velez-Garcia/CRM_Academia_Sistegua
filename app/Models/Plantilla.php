<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plantilla extends Model
{
    /** Variables que se pueden usar en el asunto y el mensaje. */
    public const VARIABLES = [
        '{nombre}' => 'Nombre completo del invitado',
        '{nombres}' => 'Solo los nombres del invitado',
        '{estudiante}' => 'Nombre del estudiante (padres)',
        '{grado}' => 'Grado y sección (padres)',
        '{titulo}' => 'Título del evento',
        '{fecha}' => 'Fecha, por ejemplo "martes 14 de octubre de 2026"',
        '{hora}' => 'Hora de inicio y fin',
        '{lugar}' => 'Lugar o plataforma virtual',
        '{sede}' => 'Nombre de la sede',
        '{colegio}' => 'Nombre del colegio',
    ];

    protected $fillable = ['nombre', 'tipo_evento', 'asunto', 'mensaje', 'predeterminada'];

    protected function casts(): array
    {
        return ['predeterminada' => 'boolean'];
    }

    public static function predeterminadaPara(string $tipoEvento): ?self
    {
        return static::where('tipo_evento', $tipoEvento)->orderByDesc('predeterminada')->orderBy('id')->first();
    }

    /** Reemplaza las variables con los datos del evento y (si se indica) del invitado. */
    public static function rellenar(?string $texto, Evento $evento, ?Contacto $contacto = null): string
    {
        $lugar = $evento->es_virtual ? 'en línea ('.$evento->plataforma.')' : $evento->lugar;

        return strtr((string) $texto, [
            '{nombre}' => $contacto?->nombre_completo ?? 'Nombre Apellido',
            '{nombres}' => $contacto?->nombres ?? 'Nombre',
            '{estudiante}' => $contacto?->estudiante ?: 'su hijo(a)',
            '{grado}' => $contacto?->grado_seccion ?? '',
            '{titulo}' => $evento->titulo,
            '{fecha}' => $evento->inicio->translatedFormat('l j \d\e F \d\e Y'),
            '{hora}' => $evento->inicio->format('H:i').' a '.$evento->fin->format('H:i').' h',
            '{lugar}' => (string) $lugar,
            '{sede}' => $evento->sede->nombre,
            '{colegio}' => config('colegio.nombre'),
        ]);
    }
}
