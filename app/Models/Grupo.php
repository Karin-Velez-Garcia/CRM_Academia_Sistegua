<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Grupo extends Model
{
    public const MIXTO = 'mixto';

    public const TIPOS = [
        Contacto::PADRE => 'Padres de familia',
        Contacto::CATEDRATICO => 'Catedráticos',
        self::MIXTO => 'Mixto',
    ];

    protected $fillable = ['nombre', 'descripcion', 'tipo', 'sede_id'];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function contactos(): BelongsToMany
    {
        return $this->belongsToMany(Contacto::class);
    }

    /**
     * Grupos donde puede entrar un contacto: mismo tipo (o mixto) y su sede (o todas).
     */
    public function scopeCompatibles(Builder $query, string $tipo, ?int $sedeId = null): Builder
    {
        return $query->whereIn('tipo', [$tipo, self::MIXTO])
            ->when($sedeId, fn ($q) => $q->where(fn ($w) => $w->whereNull('sede_id')->orWhere('sede_id', $sedeId)));
    }

    public function admite(Contacto $contacto): bool
    {
        return in_array($this->tipo, [$contacto->tipo, self::MIXTO], true)
            && ($this->sede_id === null || $this->sede_id === $contacto->sede_id);
    }
}
