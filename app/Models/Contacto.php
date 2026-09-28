<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Contacto extends Model
{
    public const PADRE = 'padre';
    public const CATEDRATICO = 'catedratico';

    /**
     * Configuración de cada tipo: segmento de la URL, textos y campos propios.
     */
    public const TIPOS = [
        'padres' => [
            'tipo' => self::PADRE,
            'plural' => 'Padres de familia',
            'singular' => 'padre de familia',
            'nuevo' => 'Nuevo padre de familia',
            'icono' => 'ki-people',
        ],
        'catedraticos' => [
            'tipo' => self::CATEDRATICO,
            'plural' => 'Catedráticos',
            'singular' => 'catedrático',
            'nuevo' => 'Nuevo catedrático',
            'icono' => 'ki-teacher',
        ],
    ];

    protected $fillable = [
        'tipo', 'sede_id', 'nombres', 'apellidos', 'dpi', 'correo', 'telefono',
        'estudiante', 'grado_seccion', 'area', 'acepta_correos',
    ];

    protected $attributes = ['acepta_correos' => true];

    protected function casts(): array
    {
        return ['acepta_correos' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Contacto $c) {
            $c->token ??= (string) Str::uuid();
        });
        static::saving(function (Contacto $c) {
            $c->correo = $c->correo ? Str::lower(trim($c->correo)) : null;
        });
    }

    public static function segmentoDe(string $tipo): string
    {
        return $tipo === self::PADRE ? 'padres' : 'catedraticos';
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class)->orderBy('nombre');
    }

    public function invitaciones(): HasMany
    {
        return $this->hasMany(Invitacion::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    public function getInicialesAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->nombres, 0, 1).mb_substr($this->apellidos, 0, 1));
    }

    public function scopeTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeBuscar(Builder $query, ?string $texto): Builder
    {
        if (! $texto) {
            return $query;
        }

        // Cada palabra debe aparecer en algún campo: "Juan Pérez" encuentra nombres=Juan, apellidos=Pérez
        foreach (preg_split('/\s+/', trim($texto)) as $palabra) {
            $query->where(function (Builder $q) use ($palabra) {
                foreach (['nombres', 'apellidos', 'correo', 'dpi', 'telefono', 'estudiante', 'grado_seccion', 'area'] as $campo) {
                    $q->orWhere($campo, 'like', "%{$palabra}%");
                }
            });
        }

        return $query;
    }
}
