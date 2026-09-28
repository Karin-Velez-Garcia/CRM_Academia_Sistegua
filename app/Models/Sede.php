<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sede extends Model
{
    protected $fillable = ['nombre', 'municipio_id', 'direccion', 'telefono', 'correo', 'activa'];

    protected $attributes = ['activa' => true];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(Contacto::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    /**
     * Busca una sede por nombre sin importar mayúsculas ni tildes ("salama" = "Salamá").
     */
    public static function porNombre(?string $nombre, iterable $sedes): ?self
    {
        $buscado = self::normalizar($nombre);
        if ($buscado === '') {
            return null;
        }

        foreach ($sedes as $sede) {
            if (self::normalizar($sede->nombre) === $buscado) {
                return $sede;
            }
        }

        return null;
    }

    public static function normalizar(?string $texto): string
    {
        return Str::of((string) $texto)->ascii()->lower()->squish()->replaceStart('sede ', '')->value();
    }
}
