<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitacion extends Model
{
    protected $table = 'invitaciones';

    public const PENDIENTE = 'pendiente';
    public const ENVIADA = 'enviada';
    public const FALLIDA = 'fallida';
    /** Registro solo para asistencia: la persona no tiene correo o llegó sin invitación. */
    public const NO_ENVIADA = 'no_enviada';

    public const CONFIRMADA = 'confirmada';
    public const RECHAZADA = 'rechazada';

    protected $fillable = [
        'evento_id', 'contacto_id', 'correo', 'estado_envio', 'error', 'respuesta', 'comentario',
        'respuesta_por', 'enviada_at', 'vista_at', 'respondida_at', 'recordatorio_at',
        'asistio', 'asistencia_at', 'asistencia_por', 'asistencia_metodo',
    ];

    /** Cómo se registró la asistencia. */
    public const METODOS = [
        'manual' => 'Lista del colegio',
        'qr_evento' => 'QR del evento',
        'qr_personal' => 'QR personal',
    ];

    /**
     * Marca presente o ausente a una persona en un evento. Si no tenía invitación (sin correo o llegó sin invitación)
     * se crea un registro "no enviada" solo para la asistencia.
     */
    public static function registrarAsistencia(Evento $evento, Contacto $contacto, bool $presente, ?int $usuarioId, string $metodo): self
    {
        $inv = static::firstOrNew(['evento_id' => $evento->id, 'contacto_id' => $contacto->id]);
        if (! $inv->exists) {
            $inv->fill(['estado_envio' => self::NO_ENVIADA, 'correo' => $contacto->correo]);
        }
        if ($inv->asistio !== $presente) {
            $inv->fill(['asistio' => $presente, 'asistencia_at' => now(), 'asistencia_por' => $usuarioId, 'asistencia_metodo' => $metodo]);
        }
        $inv->save();

        return $inv;
    }

    /** Contenido del QR personal: el personal del colegio lo escanea en la entrada. */
    public function urlEscaneo(): string
    {
        return route('escaneo.show', $this->token);
    }

    protected $attributes = ['estado_envio' => self::PENDIENTE];

    protected function casts(): array
    {
        return [
            'enviada_at' => 'datetime',
            'vista_at' => 'datetime',
            'respondida_at' => 'datetime',
            'recordatorio_at' => 'datetime',
            'asistio' => 'boolean',
            'asistencia_at' => 'datetime',
        ];
    }

    public function scopeAsistentes(Builder $q): Builder
    {
        return $q->where('asistio', true);
    }

    /** Código corto y único para verificar la constancia (se genera la primera vez). */
    public function codigoConstancia(): string
    {
        if (! $this->codigo_constancia) {
            do {
                $codigo = strtoupper(Str::random(4).'-'.Str::random(4));
                $codigo = strtr($codigo, ['0' => 'X', 'O' => 'Y', '1' => 'Z', 'I' => 'W', 'L' => 'K']);
            } while (static::where('codigo_constancia', $codigo)->exists());
            $this->forceFill(['codigo_constancia' => $codigo])->save();
        }

        return $this->codigo_constancia;
    }

    protected static function booted(): void
    {
        static::creating(function (Invitacion $i) {
            $i->token ??= (string) Str::uuid();
        });
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class);
    }

    public function scopeSinRespuesta(Builder $q): Builder
    {
        return $q->whereNull('respuesta');
    }

    public function scopeConfirmadas(Builder $q): Builder
    {
        return $q->where('respuesta', self::CONFIRMADA);
    }

    /** Filtro de la lista de seguimiento. */
    public function scopeSituacion(Builder $q, ?string $situacion): Builder
    {
        return match ($situacion) {
            'confirmadas' => $q->where('respuesta', self::CONFIRMADA),
            'rechazadas' => $q->where('respuesta', self::RECHAZADA),
            'sin_respuesta' => $q->whereNull('respuesta')->where('estado_envio', self::ENVIADA),
            'no_vistas' => $q->whereNull('vista_at')->whereNull('respuesta')->where('estado_envio', self::ENVIADA),
            'fallidas' => $q->where('estado_envio', self::FALLIDA),
            'pendientes' => $q->where('estado_envio', self::PENDIENTE),
            default => $q,
        };
    }

    public function getSituacionAttribute(): array
    {
        return match (true) {
            $this->respuesta === self::CONFIRMADA => ['Confirmó', 'success', 'ki-check-circle'],
            $this->respuesta === self::RECHAZADA => ['No asistirá', 'danger', 'ki-cross-circle'],
            $this->estado_envio === self::FALLIDA => ['Error de envío', 'danger', 'ki-information'],
            $this->estado_envio === self::PENDIENTE => ['Pendiente de envío', 'warning', 'ki-time'],
            $this->vista_at !== null => ['Vio la invitación', 'info', 'ki-eye'],
            default => ['Enviada, sin respuesta', 'dark', 'ki-sms'],
        };
    }

    public function url(?string $respuesta = null): string
    {
        return route('invitacion.show', ['token' => $this->token] + ($respuesta ? ['r' => $respuesta] : []));
    }
}
