<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ROL_ADMINISTRADOR = 'Administrador';

    /**
     * Valores por defecto (coinciden con los de la migración).
     */
    protected $attributes = [
        'activo' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'apellidos',
        'email',
        'dpi',
        'telefono',
        'sede_id',
        'activo',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'activo' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Sede a la que está limitado el usuario (null = puede ver todas).
     * El Administrador nunca queda limitado.
     */
    public function sedeRestringida(): ?int
    {
        return $this->sede_id && ! $this->hasRole(self::ROL_ADMINISTRADOR) ? $this->sede_id : null;
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->name.' '.$this->apellidos);
    }

    public function getInicialesAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1).mb_substr((string) $this->apellidos, 0, 1));
    }

    public function scopeBuscar(Builder $query, ?string $texto): Builder
    {
        if (! $texto) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($texto) {
            $q->where('name', 'like', "%{$texto}%")
                ->orWhere('apellidos', 'like', "%{$texto}%")
                ->orWhere('email', 'like', "%{$texto}%")
                ->orWhere('dpi', 'like', "%{$texto}%");
        });
    }
}
