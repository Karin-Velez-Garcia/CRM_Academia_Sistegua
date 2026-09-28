<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plantilla extends Model
{
    /** Variables que se pueden usar en el asunto y el mensaje. */
    public const VARIABLES = [
        '{nombre}' => 'Nombre completo del invitado',
        '{nombres}' => 'Solo los nombres del invitado',
        '{empresa}' => 'Empresa o negocio del cliente',
        '{oficio}' => 'Oficio del cliente (instalador, contratista…)',
        '{titulo}' => 'Título de la capacitación',
        '{fecha}' => 'Fecha, por ejemplo "martes 14 de octubre de 2026"',
        '{hora}' => 'Hora de inicio y fin',
        '{lugar}' => 'Lugar o plataforma virtual',
        '{sede}' => 'Nombre de la sede',
        '{academia}' => 'Nombre de la academia',
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
            '{empresa}' => $contacto?->empresa ?: 'su empresa',
            '{oficio}' => $contacto?->oficio ?? '',
            '{titulo}' => $evento->titulo,
            '{fecha}' => $evento->inicio->translatedFormat('l j \d\e F \d\e Y'),
            '{hora}' => $evento->inicio->format('H:i').' a '.$evento->fin->format('H:i').' h',
            '{lugar}' => (string) $lugar,
            '{sede}' => $evento->sede->nombre,
            '{academia}' => config('academia.nombre'),
        ]);
    }
}
