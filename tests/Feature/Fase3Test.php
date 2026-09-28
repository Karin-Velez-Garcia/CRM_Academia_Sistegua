<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Grupo;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\SedesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase3Test extends TestCase
{
    use RefreshDatabase;

    private Sede $sanarate;
    private Sede $coban;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, SedesSeeder::class, RolesPermisosSeeder::class]);
        $this->sanarate = Sede::where('nombre', 'Sanarate')->firstOrFail();
        $this->sanarate->update(['direccion' => '3a. Calle 2-45, Zona 1, Sanarate']);
        $this->coban = Sede::where('nombre', 'Cobán')->firstOrFail();
        $this->admin = User::factory()->create();
        $this->admin->assignRole(User::ROL_ADMINISTRADOR);
    }

    private function contacto(string $tipo, Sede $sede, array $datos = []): Contacto
    {
        return Contacto::create($datos + [
            'tipo' => $tipo, 'sede_id' => $sede->id, 'nombres' => 'N', 'apellidos' => 'A',
            'correo' => uniqid().'@correo.com',
        ]);
    }

    private function datosReunion(array $cambios = []): array
    {
        return $cambios + [
            'titulo' => 'Entrega de notas', 'descripcion' => 'Primer bimestre', 'sede_id' => $this->sanarate->id,
            'modalidad' => 'presencial', 'fecha' => now()->addWeek()->format('Y-m-d'),
            'hora_inicio' => '15:00', 'hora_fin' => '17:00', 'lugar' => 'Salón principal', 'para_todos' => '1',
        ];
    }

    public function test_formulario_propone_la_direccion_de_la_sede(): void
    {
        $directora = User::factory()->create(['sede_id' => $this->sanarate->id]);
        $directora->assignRole('Director de sede');

        $this->actingAs($directora)->get(route('eventos.create', 'reuniones'))
            ->assertOk()->assertSee('3a. Calle 2-45, Zona 1, Sanarate');
    }

    public function test_programar_reunion_presencial_para_todos_los_padres(): void
    {
        $this->contacto(Contacto::PADRE, $this->sanarate);
        $this->contacto(Contacto::PADRE, $this->sanarate, ['correo' => null]);
        $this->contacto(Contacto::PADRE, $this->coban);                // otra sede
        $this->contacto(Contacto::CATEDRATICO, $this->sanarate);       // otro tipo

        $this->actingAs($this->admin)->post(route('eventos.store', 'reuniones'), $this->datosReunion())
            ->assertRedirect();

        $evento = Evento::firstOrFail();
        $this->assertSame(Evento::REUNION, $evento->tipo);
        $this->assertSame('15:00', $evento->inicio->format('H:i'));
        $this->assertSame(2.0, $evento->duracion_horas);
        $this->assertSame($this->admin->id, $evento->creado_por);
        $this->assertSame(2, $evento->destinatarios()->count());
        $this->assertSame(1, $evento->destinatariosConCorreo()->count());

        $this->actingAs($this->admin)->get(route('eventos.show', ['reuniones', $evento]))
            ->assertOk()->assertSee('Salón principal')->assertSee('Todos los padres de familia');
        $this->actingAs($this->admin)->get(route('eventos.index', 'reuniones'))->assertOk()->assertSee('Entrega de notas');
    }

    public function test_capacitacion_virtual_para_un_grupo(): void
    {
        $grupo = Grupo::create(['nombre' => 'Claustro básico', 'tipo' => Contacto::CATEDRATICO, 'sede_id' => null]);
        $enSede = $this->contacto(Contacto::CATEDRATICO, $this->coban);
        $otraSede = $this->contacto(Contacto::CATEDRATICO, $this->sanarate);
        $grupo->contactos()->attach([$enSede->id, $otraSede->id]);

        $this->actingAs($this->admin)->post(route('eventos.store', 'capacitaciones'), [
            'titulo' => 'Evaluación por competencias', 'sede_id' => $this->coban->id, 'modalidad' => 'virtual',
            'fecha' => now()->addDays(3)->format('Y-m-d'), 'hora_inicio' => '08:00', 'hora_fin' => '12:30',
            'enlace' => 'https://meet.google.com/abc-defg-hij', 'facilitador' => 'Lic. Ana Pérez', 'cupo' => '30',
            'grupos' => [$grupo->id], 'lugar' => 'se ignora',
        ])->assertRedirect();

        $evento = Evento::firstOrFail();
        $this->assertSame(Evento::VIRTUAL, $evento->modalidad);
        $this->assertNull($evento->lugar);
        $this->assertSame('Google Meet', $evento->plataforma);
        $this->assertSame(4.5, $evento->duracion_horas);
        $this->assertSame(30, $evento->cupo);
        // Del grupo general solo cuentan los de la sede del evento
        $this->assertSame([$enSede->id], $evento->destinatarios()->pluck('id')->all());
    }

    public function test_validaciones_del_evento(): void
    {
        $this->actingAs($this->admin)->post(route('eventos.store', 'reuniones'), [
            'titulo' => '', 'sede_id' => $this->sanarate->id, 'modalidad' => 'virtual',
            'fecha' => 'mañana', 'hora_inicio' => '17:00', 'hora_fin' => '15:00', 'enlace' => 'no-es-enlace',
        ])->assertSessionHasErrors(['titulo', 'fecha', 'hora_fin', 'enlace']);

        // Presencial sin lugar
        $this->actingAs($this->admin)->post(route('eventos.store', 'reuniones'), $this->datosReunion(['lugar' => '']))
            ->assertSessionHasErrors('lugar');

        // Sin destinatarios
        $this->actingAs($this->admin)->post(route('eventos.store', 'reuniones'), $this->datosReunion(['para_todos' => '0']))
            ->assertSessionHasErrors('grupos');

        // Una reunión no acepta datos de capacitación
        $this->actingAs($this->admin)->post(route('eventos.store', 'reuniones'), $this->datosReunion(['cupo' => '20']))
            ->assertSessionHasErrors('cupo');

        $this->assertSame(0, Evento::count());
    }

    public function test_editar_cancelar_reactivar_duplicar_y_eliminar(): void
    {
        $this->actingAs($this->admin)->post(route('eventos.store', 'reuniones'), $this->datosReunion());
        $evento = Evento::firstOrFail();

        $this->actingAs($this->admin)->put(route('eventos.update', ['reuniones', $evento]),
            $this->datosReunion(['titulo' => 'Entrega de notas (cambio de hora)', 'hora_inicio' => '16:00', 'hora_fin' => '18:00']))
            ->assertRedirect(route('eventos.show', ['reuniones', $evento]));
        $this->assertSame('16:00', $evento->fresh()->inicio->format('H:i'));

        $this->actingAs($this->admin)->patch(route('eventos.cancelar', ['reuniones', $evento]), ['motivo' => 'Lluvia'])->assertSessionHas('success');
        $this->assertSame('cancelado', $evento->fresh()->estado);
        $this->actingAs($this->admin)->get(route('eventos.index', ['reuniones', 'estado' => 'cancelados']))->assertSee('cambio de hora');
        $this->actingAs($this->admin)->get(route('eventos.index', 'reuniones'))->assertDontSee('cambio de hora');

        $this->actingAs($this->admin)->patch(route('eventos.reactivar', ['reuniones', $evento]));
        $this->assertSame('programado', $evento->fresh()->estado);

        $this->actingAs($this->admin)->get(route('eventos.duplicar', ['reuniones', $evento]))
            ->assertOk()->assertSee('Copia de')->assertSee('Salón principal');

        // Una reunión no se abre desde la ruta de capacitaciones
        $this->actingAs($this->admin)->get(route('eventos.show', ['capacitaciones', $evento]))->assertNotFound();

        $this->actingAs($this->admin)->delete(route('eventos.destroy', ['reuniones', $evento]))->assertRedirect(route('eventos.index', 'reuniones'));
        $this->assertModelMissing($evento);
    }

    public function test_archivo_de_calendario(): void
    {
        $this->actingAs($this->admin)->post(route('eventos.store', 'reuniones'), $this->datosReunion(['titulo' => 'Reunión, general; padres']));
        $evento = Evento::firstOrFail();

        $resp = $this->actingAs($this->admin)->get(route('eventos.calendario', ['reuniones', $evento]));
        $resp->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $ics = $resp->getContent();
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('SUMMARY:Reunión\, general\; padres', $ics);
        // 15:00 en Guatemala (UTC-6) = 21:00 UTC
        $this->assertStringContainsString('DTSTART:'.$evento->inicio->format('Ymd').'T210000Z', $ics);
        $this->assertStringContainsString('LOCATION:Salón principal', $ics);
    }

    public function test_usuario_de_sede_solo_ve_sus_eventos_y_secretaria_no_crea(): void
    {
        $this->actingAs($this->admin)->post(route('eventos.store', 'reuniones'), $this->datosReunion(['titulo' => 'De Sanarate']));
        $this->actingAs($this->admin)->post(route('eventos.store', 'reuniones'), $this->datosReunion(['titulo' => 'De Cobán', 'sede_id' => $this->coban->id]));
        $deSanarate = Evento::where('titulo', 'De Sanarate')->firstOrFail();

        $director = User::factory()->create(['sede_id' => $this->coban->id]);
        $director->assignRole('Director de sede');
        $this->actingAs($director)->get(route('eventos.index', 'reuniones'))->assertSee('De Cobán')->assertDontSee('De Sanarate');
        $this->actingAs($director)->get(route('eventos.show', ['reuniones', $deSanarate]))->assertForbidden();

        $secretaria = User::factory()->create();
        $secretaria->assignRole('Secretaría');
        $this->actingAs($secretaria)->get(route('eventos.show', ['reuniones', $deSanarate]))->assertOk();
        $this->actingAs($secretaria)->get(route('eventos.create', 'reuniones'))->assertForbidden();
    }

    public function test_panel_muestra_proximos_eventos(): void
    {
        $this->actingAs($this->admin)->post(route('eventos.store', 'capacitaciones'), [
            'titulo' => 'Uso de plataformas', 'sede_id' => $this->sanarate->id, 'modalidad' => 'presencial', 'lugar' => 'Laboratorio',
            'fecha' => now()->addDay()->format('Y-m-d'), 'hora_inicio' => '09:00', 'hora_fin' => '11:00', 'para_todos' => '1',
        ]);

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Uso de plataformas');
    }
}
