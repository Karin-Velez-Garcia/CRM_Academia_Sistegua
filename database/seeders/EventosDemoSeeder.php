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
 * Capacitaciones de demostración de los últimos 11 meses y algunas próximas, con
 * invitaciones y asistencia simuladas de forma realista: quien tiene correo recibe
 * invitación; la asistencia se toma la mayoría de las veces pero no siempre se
 * completa toda la lista, igual que en el uso real.
 */
class EventosDemoSeeder extends Seeder
{
    private array $titulos = [
        'Instalación de tabla yeso: fundamentos',
        'Cielo falso suspendido paso a paso',
        'Muros divisorios en steel framing',
        'Acabados y masillado nivel 5',
        'Aislamiento acústico en construcción liviana',
        'Sistemas de fachada liviana',
        'Instalación de cielo falso mineral',
        'Cálculo y cuantificación de materiales',
        'Tabla cemento en áreas húmedas',
        'Resistencia al fuego en sistemas livianos',
        'Herramienta y equipo para el instalador',
        'Certificación de instalador: módulo práctico',
        'Novedades de producto y garantías',
        'Buenas prácticas en obra y seguridad',
    ];

    private array $lugares = [
        'Centro de capacitación',
        'Sala de demostración',
        'Taller práctico',
    ];

    private array $enlaces = [
        'https://meet.google.com/demo-sistegua-capacitacion',
        'https://zoom.us/j/00000000000',
        'https://teams.microsoft.com/l/meetup-join/demo-sistegua',
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

        $total = 25;
        $inicioRango = now()->copy()->subMonths(11)->startOfMonth();
        $finRango = now()->copy()->addWeeks(3);
        $totalDias = $inicioRango->diffInDays($finRango);

        $facilitadores = ['Ing. Mario Cabrera', 'Arq. Silvia Monterroso', 'Téc. Byron Tzoc', 'Ing. Elena Girón', 'Téc. Wilmer Pérez'];

        for ($indice = 0; $indice < $total; $indice++) {
            $sede = $sedes[$indice % $sedes->count()];
            $dia = $inicioRango->copy()->addDays((int) round($indice * $totalDias / ($total - 1)) + random_int(-3, 3));

            $esVirtual = random_int(1, 100) <= 20;
            $horas = ['08:00', '09:00', '14:00'];
            [$h, $m] = explode(':', $horas[array_rand($horas)]);
            $inicio = $dia->copy()->setTime((int) $h, (int) $m);
            $duraciones = [2, 3, 4];
            $fin = $inicio->copy()->addHours($duraciones[array_rand($duraciones)]);

            $paraTodos = random_int(1, 100) <= 45;
            $cupos = [20, 25, 30, 40];

            $evento = Evento::create([
                'tipo' => Evento::CAPACITACION,
                'titulo' => $this->titulos[$indice % count($this->titulos)],
                'descripcion' => null,
                'sede_id' => $sede->id,
                'modalidad' => $esVirtual ? Evento::VIRTUAL : Evento::PRESENCIAL,
                'inicio' => $inicio,
                'fin' => $fin,
                'lugar' => $esVirtual ? null : $this->lugares[array_rand($this->lugares)].' - Sede '.$sede->nombre,
                'enlace' => $esVirtual ? $this->enlaces[array_rand($this->enlaces)] : null,
                'facilitador' => $facilitadores[array_rand($facilitadores)],
                'cupo' => random_int(1, 100) <= 70 ? $cupos[array_rand($cupos)] : null,
                'para_todos' => $paraTodos,
                'creado_por' => $admin?->id,
            ]);

            if (! $paraTodos) {
                $grupos = Grupo::compatibles(Contacto::CLIENTE, $sede->id)->inRandomOrder()->limit(random_int(1, 2))->pluck('id');
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
