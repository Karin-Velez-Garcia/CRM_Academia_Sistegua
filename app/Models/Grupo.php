<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Grupo extends Model
{
    public const TIPOS = [
        Contacto::CLIENTE => 'Clientes',
    ];

    protected $fillable = ['nombre', 'descripcion', 'tipo', 'sede_id'];

    protected $attributes = ['tipo' => Contacto::CLIENTE];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function contactos(): BelongsToMany
    {
        return $this->belongsToMany(Contacto::class);
    }

    /**
     * Grupos donde puede entrar un contacto: mismo tipo y su sede (o todas).
     */
    public function scopeCompatibles(Builder $query, string $tipo, ?int $sedeId = null): Builder
    {
        return $query->where('tipo', $tipo)
            ->when($sedeId, fn ($q) => $q->where(fn ($w) => $w->whereNull('sede_id')->orWhere('sede_id', $sedeId)));
    }

    public function admite(Contacto $contacto): bool
    {
        return $this->tipo === $contacto->tipo
            && ($this->sede_id === null || $this->sede_id === $contacto->sede_id);
    }
}
