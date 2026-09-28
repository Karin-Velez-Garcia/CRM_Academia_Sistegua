<?php

namespace App\Services;

use App\Jobs\EnviarCorreoEvento;
use App\Models\Contacto;
use App\Models\Envio;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Plantilla;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Decide a quién se escribe en cada caso y despacha los correos de un evento.
 */
class InvitacionesEvento
{
    /** Textos por defecto de los correos que no son la invitación inicial. */
    public const TEXTOS = [
        'recordatorio' => [
            'asunto' => 'Recordatorio: {titulo} — {fecha}',
            'mensaje' => "Estimado(a) {nombre}:\n\nLe recordamos la actividad \"{titulo}\" del {fecha}, de {hora}, en {lugar}.\n\nTodavía no hemos recibido su respuesta. Le agradecemos confirmar si podrá asistir.",
        ],
        'cambio' => [
            'asunto' => 'Cambio en: {titulo}',
            'mensaje' => "Estimado(a) {nombre}:\n\nLe informamos que la actividad \"{titulo}\" tuvo cambios. Los datos actualizados son: {fecha}, de {hora}, en {lugar}.\n\nSi su disponibilidad cambió, puede actualizar su respuesta con los botones de este correo.",
        ],
        'cancelacion' => [
            'asunto' => 'Cancelada: {titulo}',
            'mensaje' => "Estimado(a) {nombre}:\n\nLe informamos que la actividad \"{titulo}\" programada para el {fecha} fue cancelada.\n\nDisculpe los inconvenientes.",
        ],
    ];

    /** Recordatorio automático del día anterior: un texto para quien confirmó y otro para quien no ha respondido. */
    public const TEXTOS_AUTOMATICOS = [
        'confirmados' => [
            'asunto' => 'Le esperamos: {titulo} — {fecha}',
            'mensaje' => "Estimado(a) {nombre}:\n\nGracias por confirmar su asistencia. Le recordamos que le esperamos el {fecha}, de {hora}, en {lugar}.\n\nSi a última hora no puede asistir, por favor avísenos con el botón de este correo.",
        ],
        'sin_respuesta' => [
            'asunto' => 'Recordatorio: {titulo} — {fecha}',
            'mensaje' => "Estimado(a) {nombre}:\n\nLe recordamos la actividad \"{titulo}\" del {fecha}, de {hora}, en {lugar}.\n\nAún no hemos recibido su confirmación. Le agradecemos indicarnos si podrá asistir.",
        ],
    ];

    public function __construct(private readonly Evento $evento)
    {
    }

    public static function textoPorDefecto(string $motivo, Evento $evento): array
    {
        if ($motivo === 'invitacion') {
            $p = Plantilla::predeterminadaPara($evento->tipo);

            return ['asunto' => $p?->asunto ?? 'Invitación: {titulo}', 'mensaje' => $p?->mensaje ?? "Estimado(a) {nombre}:\n\nLe invitamos a \"{titulo}\"."];
        }

        return self::TEXTOS[$motivo] ?? self::TEXTOS_AUTOMATICOS['sin_respuesta'];
    }

    /** Destinatarios con correo que todavía no tienen invitación a este evento. */
    public function porInvitar(): Builder
    {
        return $this->evento->destinatariosConCorreo()
            ->whereDoesntHave('invitaciones', fn ($q) => $q->where('evento_id', $this->evento->id)
                ->where('estado_envio', '!=', Invitacion::NO_ENVIADA));
    }

    /** Invitaciones enviadas que aún no tienen respuesta. */
    public function porRecordar(): Builder
    {
        return $this->recordables()->whereNull('respuesta');
    }

    /** Invitaciones enviadas de quienes confirmaron. */
    public function confirmadosPorRecordar(): Builder
    {
        return $this->recordables()->where('respuesta', Invitacion::CONFIRMADA);
    }

    private function recordables(): Builder
    {
        return $this->evento->invitaciones()->getQuery()
            ->where('estado_envio', Invitacion::ENVIADA)
            ->whereHas('contacto', fn ($q) => $q->where('acepta_correos', true));
    }

    /** Invitaciones a quienes avisar de un cambio o una cancelación (no a quienes dijeron que no irán). */
    public function porAvisar(): Builder
    {
        return $this->evento->invitaciones()->getQuery()
            ->where('estado_envio', Invitacion::ENVIADA)
            ->where(fn ($q) => $q->whereNull('respuesta')->orWhere('respuesta', Invitacion::CONFIRMADA))
            ->whereHas('contacto', fn ($q) => $q->where('acepta_correos', true));
    }

    public function invitar(string $asunto, string $mensaje, ?User $usuario): int
    {
        $contactos = $this->porInvitar()->get();
        if ($contactos->isEmpty()) {
            return 0;
        }

        // Si ya estaba en la lista de asistencia (sin correo en ese momento), se reutiliza su registro
        $invitaciones = DB::transaction(fn () => $contactos->map(fn (Contacto $c) => Invitacion::updateOrCreate(
            ['evento_id' => $this->evento->id, 'contacto_id' => $c->id],
            ['correo' => $c->correo, 'estado_envio' => Invitacion::PENDIENTE],
        )));

        $this->registrarEnvio('invitacion', $asunto, $mensaje, $invitaciones->count(), $usuario);
        $invitaciones->each(fn (Invitacion $i) => $this->despachar($i, 'invitacion', $asunto, $mensaje));

        return $invitaciones->count();
    }

    /**
     * Recordatorio desde el botón de la ficha del evento. Cada grupo lleva su propio texto:
     * $textos = ['sin_respuesta' => ['asunto' => …, 'mensaje' => …], 'confirmados' => [...]].
     * Solo se escribe a los grupos incluidos en $textos. Devuelve [confirmados, sin respuesta].
     */
    public function recordar(array $textos, ?User $usuario): array
    {
        $grupos = [
            'confirmados' => isset($textos['confirmados']) ? $this->confirmadosPorRecordar()->get() : collect(),
            'sin_respuesta' => isset($textos['sin_respuesta']) ? $this->porRecordar()->get() : collect(),
        ];

        foreach ($grupos as $clave => $invitaciones) {
            if ($invitaciones->isNotEmpty()) {
                $this->enviarA($invitaciones, 'recordatorio', $textos[$clave]['asunto'], $textos[$clave]['mensaje'], $usuario);
            }
        }

        if ($grupos['confirmados']->isNotEmpty() || $grupos['sin_respuesta']->isNotEmpty()) {
            $this->evento->forceFill(['recordatorio_enviado_at' => now()])->save();
        }

        return [$grupos['confirmados']->count(), $grupos['sin_respuesta']->count()];
    }

    public function avisar(string $motivo, ?User $usuario, ?string $asunto = null, ?string $mensaje = null): int
    {
        $texto = self::TEXTOS[$motivo];
        $mensaje ??= $texto['mensaje'];
        if ($motivo === 'cancelacion' && $this->evento->motivo_cancelacion) {
            $mensaje .= "\n\nMotivo: {$this->evento->motivo_cancelacion}";
        }

        return $this->enviarA($this->porAvisar()->get(), $motivo, $asunto ?? $texto['asunto'], $mensaje, $usuario);
    }

    /**
     * Recordatorio automático (lo lanza la tarea programada). No se envía a quienes dijeron que no,
     * ni a quienes fueron invitados después del momento del recordatorio (ya recibieron su invitación reciente).
     * Devuelve [confirmados, sin respuesta] a quienes se escribió.
     */
    public function recordatorioAutomatico(): array
    {
        $momento = $this->evento->momentoRecordatorio();
        $base = fn () => $this->evento->invitaciones()->getQuery()
            ->where('estado_envio', Invitacion::ENVIADA)
            ->where('enviada_at', '<', $momento)
            ->whereHas('contacto', fn ($q) => $q->where('acepta_correos', true));

        $grupos = [
            'confirmados' => $base()->where('respuesta', Invitacion::CONFIRMADA)->get(),
            'sin_respuesta' => $base()->whereNull('respuesta')->get(),
        ];

        foreach ($grupos as $clave => $invitaciones) {
            $texto = self::TEXTOS_AUTOMATICOS[$clave];
            if ($invitaciones->isNotEmpty()) {
                $this->registrarEnvio('recordatorio_auto', $texto['asunto'], $texto['mensaje'], $invitaciones->count(), null);
            }
            $invitaciones->each(fn (Invitacion $i) => $this->despachar($i, 'recordatorio', $texto['asunto'], $texto['mensaje']));
        }

        $this->evento->forceFill(['recordatorio_enviado_at' => now()])->save();

        return [$grupos['confirmados']->count(), $grupos['sin_respuesta']->count()];
    }

    /** Vuelve a enviar la invitación a una persona (p. ej. después de corregir su correo). */
    public function reenviar(Invitacion $invitacion, ?User $usuario): void
    {
        $invitacion->update([
            'correo' => $invitacion->contacto->correo ?? $invitacion->correo,
            'estado_envio' => Invitacion::PENDIENTE, 'error' => null,
        ]);
        $texto = self::textoPorDefecto('invitacion', $this->evento);
        $this->registrarEnvio('invitacion', $texto['asunto'], $texto['mensaje'], 1, $usuario);
        $this->despachar($invitacion, 'invitacion', $texto['asunto'], $texto['mensaje']);
    }

    private function enviarA($invitaciones, string $motivo, string $asunto, string $mensaje, ?User $usuario): int
    {
        if ($invitaciones->isEmpty()) {
            return 0;
        }
        $this->registrarEnvio($motivo, $asunto, $mensaje, $invitaciones->count(), $usuario);
        $invitaciones->each(fn (Invitacion $i) => $this->despachar($i, $motivo, $asunto, $mensaje));

        return $invitaciones->count();
    }

    private function despachar(Invitacion $inv, string $motivo, string $asunto, string $mensaje): void
    {
        $inv->setRelation('evento', $this->evento);
        $contacto = $inv->contacto;

        EnviarCorreoEvento::dispatch(
            $inv,
            $motivo,
            Plantilla::rellenar($asunto, $this->evento, $contacto),
            Plantilla::rellenar($mensaje, $this->evento, $contacto),
        );
    }

    private function registrarEnvio(string $motivo, string $asunto, string $mensaje, int $total, ?User $usuario): void
    {
        Envio::create([
            'evento_id' => $this->evento->id, 'motivo' => $motivo, 'asunto' => $asunto,
            'mensaje' => $mensaje, 'total' => $total, 'user_id' => $usuario?->id,
        ]);
    }
}
