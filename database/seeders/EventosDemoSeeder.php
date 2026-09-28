<?php

namespace Database\Seeders;

use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Grupo;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Eventos de demostración (reuniones y capacitaciones) de los últimos 11 meses y
 * algunas próximas, con invitaciones y asistencia simuladas de forma realista:
 * quien tiene correo recibe invitación; la asistencia se toma la mayoría de las
 * veces pero no siempre se completa toda la lista, igual que en el uso real.
 */
class EventosDemoSeeder extends Seeder
{
    private array $tituloReuniones = [
        'Reunión general de padres de familia',
        'Entrega de notas - Primer bimestre',
        'Entrega de notas - Segundo bimestre',
        'Entrega de notas - Tercer bimestre',
        'Entrega de notas - Cuarto bimestre',
        'Reunión de padres de Preprimaria',
        'Reunión de padres de Primaria',
        'Reunión de padres de Básico',
        'Jornada de puertas abiertas',
        'Reunión informativa: inicio de ciclo escolar',
        'Asamblea de padres de familia',
        'Reunión de seguimiento académico',
        'Reunión de padres: actividades de fin de año',
    ];

    private array $tituloCapacitaciones = [
        'Capacitación: Manejo positivo del aula',
        'Capacitación en primeros auxilios',
        'Taller de planificación didáctica',
        'Capacitación en evaluación por competencias',
        'Taller de convivencia y disciplina escolar',
        'Capacitación en herramientas digitales para el aula',
        'Taller de atención a la diversidad',
        'Capacitación en prevención de violencia escolar',
        'Jornada de actualización docente',
        'Taller de trabajo colaborativo entre catedráticos',
    ];

    private array $lugares = [
        'Salón de Usos Múltiples',
        'Auditorio del colegio',
        'Salón de proyecciones',
    ];

    private array $enlaces = [
        'https://meet.google.com/demo-esteca-reunion',
        'https://zoom.us/j/00000000000',
        'https://teams.microsoft.com/l/meetup-join/demo-esteca',
    ];

    private array $comentariosRechazo = [
        'No podré asistir por motivos de trabajo.',
        'Tengo una cita médica ese día.',
        'Estaré fuera de la ciudad esa fecha.',
        'Un compromiso familiar no me permite asistir.',
        'Problemas de transporte para llegar a esa hora.',
    ];

    public function run(): void
    {
        $admin = User::query()->orderBy('id')->first();
        $sedes = Sede::all();

        $tipos = array_merge(array_fill(0, 15, Evento::REUNION), array_fill(0, 10, Evento::CAPACITACION));
        shuffle($tipos);

        $inicioRango = now()->copy()->subMonths(11)->startOfMonth();
        $finRango = now()->copy()->addWeeks(3);
        $totalDias = $inicioRango->diffInDays($finRango);

        foreach ($tipos as $indice => $tipo) {
            $sede = $sedes[$indice % $sedes->count()];
            $dia = $inicioRango->copy()->addDays((int) round($indice * $totalDias / (count($tipos) - 1)) + random_int(-3, 3));

            $esVirtual = random_int(1, 100) <= 20;
            $horaInicio = $tipo === Evento::REUNION
                ? ['17:00', '17:30', '18:00'][array_rand(['17:00', '17:30', '18:00'])]
                : ['08:00', '09:00', '14:00'][array_rand(['08:00', '09:00', '14:00'])];
            [$h, $m] = explode(':', $horaInicio);
            $inicio = $dia->copy()->setTime((int) $h, (int) $m);
            $duracionHoras = $tipo === Evento::REUNION ? [1.5, 2][array_rand([1.5, 2])] : [2, 3, 4][array_rand([2, 3, 4])];
            $fin = $inicio->copy()->addMinutes((int) ($duracionHoras * 60));

            $paraTodos = random_int(1, 100) <= 55;
            $publico = $tipo === Evento::REUNION ? Contacto::PADRE : Contacto::CATEDRATICO;

            $titulos = $tipo === Evento::REUNION ? $this->tituloReuniones : $this->tituloCapacitaciones;

            $evento = Evento::create([
                'tipo' => $tipo,
                'titulo' => $titulos[$indice % count($titulos)],
                'descripcion' => null,
                'sede_id' => $sede->id,
                'modalidad' => $esVirtual ? Evento::VIRTUAL : Evento::PRESENCIAL,
                'inicio' => $inicio,
                'fin' => $fin,
                'lugar' => $esVirtual ? null : $this->lugares[array_rand($this->lugares)].' - Sede '.$sede->nombre,
                'enlace' => $esVirtual ? $this->enlaces[array_rand($this->enlaces)] : null,
                'facilitador' => $tipo === Evento::CAPACITACION
                    ? optional(Contacto::tipo(Contacto::CATEDRATICO)->where('sede_id', $sede->id)->inRandomOrder()->first())->nombre_completo
                    : null,
                'cupo' => $tipo === Evento::CAPACITACION && random_int(1, 100) <= 60 ? [25, 30, 40, 50][array_rand([25, 30, 40, 50])] : null,
                'para_todos' => $paraTodos,
                'creado_por' => $admin?->id,
            ]);

            if (! $paraTodos) {
                $grupos = Grupo::compatibles($publico, $sede->id)->inRandomOrder()->limit(random_int(1, 2))->pluck('id');
                if ($grupos->isNotEmpty()) {
                    $evento->grupos()->sync($grupos);
                } else {
                    $evento->update(['para_todos' => true]);
                }
            }

            $this->generarInvitaciones($evento->fresh(), $admin?->id);
        }
    }

    private function generarInvitaciones(Evento $evento, ?int $adminId): void
    {
        $destinatarios = $evento->destinatarios()->get();
        $pasado = $evento->inicio->isPast();
        $filas = [];
        $ahora = now();

        foreach ($destinatarios as $contacto) {
            $tieneCorreo = $contacto->correo && $contacto->acepta_correos;

            if (! $tieneCorreo) {
                // Sin correo: solo queda registro si el personal tomó asistencia de esta persona.
                if (! $pasado || random_int(1, 100) > 80) {
                    continue;
                }
                $presente = random_int(1, 100) <= 40;
                $filas[] = [
                    'evento_id' => $evento->id,
                    'contacto_id' => $contacto->id,
                    'token' => (string) Str::uuid(),
                    'correo' => null,
                    'estado_envio' => 'no_enviada',
                    'error' => null,
                    'respuesta' => null,
                    'comentario' => null,
                    'respuesta_por' => null,
                    'enviada_at' => null,
                    'vista_at' => null,
                    'respondida_at' => null,
                    'recordatorio_at' => null,
                    'asistio' => (int) $presente,
                    'asistencia_at' => $evento->inicio->copy()->addMinutes(random_int(0, 20)),
                    'asistencia_por' => $adminId,
                    'asistencia_metodo' => 'manual',
                    'codigo_constancia' => null,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];

                continue;
            }

            $enviadaAt = $evento->inicio->copy()->subDays(random_int(5, 12));
            $vista = random_int(1, 100) <= 70;
            $vistaAt = $vista ? $enviadaAt->copy()->addHours(random_int(1, 48)) : null;

            $rand = random_int(1, 100);
            $respuesta = match (true) {
                $rand <= 55 => 'confirmada',
                $rand <= 65 => 'rechazada',
                default => null,
            };
            $respondidaAt = $respuesta ? $enviadaAt->copy()->addHours(random_int(2, 72)) : null;
            $respuestaPor = $respuesta ? (random_int(1, 100) <= 85 ? 'invitado' : 'personal') : null;
            $comentario = ($respuesta === 'rechazada' && random_int(1, 100) <= 50)
                ? $this->comentariosRechazo[array_rand($this->comentariosRechazo)]
                : null;
            $recordatorioAt = ($pasado && random_int(1, 100) <= 50) ? $evento->inicio->copy()->subDay()->setTime(7, 0) : null;

            $asistio = null;
            $asistenciaAt = null;
            $asistenciaMetodo = null;
            if ($pasado && random_int(1, 100) <= 92) {
                $probabilidad = match ($respuesta) {
                    'confirmada' => 85,
                    'rechazada' => 5,
                    default => 30,
                };
                $asistio = (int) (random_int(1, 100) <= $probabilidad);
                $asistenciaAt = $evento->inicio->copy()->addMinutes(random_int(-10, 20));
                $asistenciaMetodo = 'manual';
            }

            $filas[] = [
                'evento_id' => $evento->id,
                'contacto_id' => $contacto->id,
                'token' => (string) Str::uuid(),
                'correo' => $contacto->correo,
                'estado_envio' => 'enviada',
                'error' => null,
                'respuesta' => $respuesta,
                'comentario' => $comentario,
                'respuesta_por' => $respuestaPor,
                'enviada_at' => $enviadaAt,
                'vista_at' => $vistaAt,
                'respondida_at' => $respondidaAt,
                'recordatorio_at' => $recordatorioAt,
                'asistio' => $asistio,
                'asistencia_at' => $asistenciaAt,
                'asistencia_por' => $asistio !== null ? $adminId : null,
                'asistencia_metodo' => $asistenciaMetodo,
                'codigo_constancia' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($filas, 400) as $lote) {
            DB::table('invitaciones')->insert($lote);
        }
    }
}
