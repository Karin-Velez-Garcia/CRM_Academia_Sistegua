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
    public const CLIENTE = 'cliente';

    /**
     * Configuración de cada tipo: segmento de la URL, textos y campos propios.
     * Hoy la academia solo capacita clientes; la estructura admite agregar otro
     * tipo (p. ej. distribuidores) sin tocar controladores ni rutas.
     */
    public const TIPOS = [
        'clientes' => [
            'tipo' => self::CLIENTE,
            'plural' => 'Clientes',
            'singular' => 'cliente',
            'nuevo' => 'Nuevo cliente',
            'icono' => 'ki-people',
        ],
    ];

    protected $fillable = [
        'tipo', 'sede_id', 'nombres', 'apellidos', 'dpi', 'correo', 'telefono',
        'empresa', 'oficio', 'acepta_correos', 'activo',
    ];

    protected $attributes = ['tipo' => self::CLIENTE, 'acepta_correos' => true, 'activo' => true];

    protected function casts(): array
    {
        return ['acepta_correos' => 'boolean', 'activo' => 'boolean'];
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
        return 'clientes';
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

    /** Los desactivados conservan su historial, pero ya no reciben invitaciones ni aparecen en las listas. */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('contactos.activo', true);
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
                foreach (['nombres', 'apellidos', 'correo', 'dpi', 'telefono', 'empresa', 'oficio'] as $campo) {
                    $q->orWhere($campo, 'like', "%{$palabra}%");
                }
            });
        }

        return $query;
    }
}
