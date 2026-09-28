<?php

namespace Tests\Feature;

use App\Mail\CorreoEvento;
use App\Models\Contacto;
use App\Models\Envio;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\PlantillasSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\SedesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecordatorioAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    private Sede $sede;
    private Evento $evento;
    private Carbon $inicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, SedesSeeder::class, RolesPermisosSeeder::class, PlantillasSeeder::class]);
        Mail::fake();
        // Estas pruebas cubren el envío automático (opcional, para un servidor con tarea programada)
        config(['colegio.recordatorio' => ['automatico' => true, 'dias_antes' => 1, 'hora' => '07:00']]);

        // Hoy es lunes 10:00; el evento es el jueves a las 15:00
        $this->travelTo(Carbon::parse('2026-10-05 10:00'));
        $this->inicio = Carbon::parse('2026-10-08 15:00');
        $this->sede = Sede::where('nombre', 'Sanarate')->firstOrFail();
        $this->evento = Evento::create([
            'tipo' => Evento::REUNION, 'titulo' => 'Entrega de notas', 'sede_id' => $this->sede->id,
            'modalidad' => Evento::PRESENCIAL, 'lugar' => 'Salón', 'para_todos' => true,
            'inicio' => $this->inicio, 'fin' => $this->inicio->copy()->addHours(2),
        ]);
    }

    private function invitado(?string $respuesta = null, array $datos = []): Invitacion
    {
        $c = Contacto::create(($datos['contacto'] ?? []) + [
            'tipo' => Contacto::PADRE, 'sede_id' => $this->sede->id, 'nombres' => 'N'.uniqid(), 'apellidos' => 'A', 'correo' => uniqid().'@correo.com',
        ]);

        return Invitacion::create([
            'evento_id' => $this->evento->id, 'contacto_id' => $c->id, 'correo' => $c->correo,
            'estado_envio' => Invitacion::ENVIADA, 'enviada_at' => $datos['enviada_at'] ?? now(), 'respuesta' => $respuesta,
        ]);
    }

    public function test_sale_el_dia_anterior_a_la_hora_y_una_sola_vez(): void
    {
        $confirmo = $this->invitado(Invitacion::CONFIRMADA);
        $sinRespuesta = $this->invitado();
        $dijoQueNo = $this->invitado(Invitacion::RECHAZADA);

        // Miércoles 06:45: todavía no
        $this->travelTo(Carbon::parse('2026-10-07 06:45'));
        $this->artisan('recordatorios:enviar')->expectsOutput('No hay recordatorios pendientes.')->assertSuccessful();
        Mail::assertNothingSent();

        // Miércoles 07:00: sale
        $this->travelTo(Carbon::parse('2026-10-07 07:00'));
        $this->artisan('recordatorios:enviar')->assertSuccessful();

        Mail::assertSent(CorreoEvento::class, 2);
        Mail::assertSent(CorreoEvento::class, fn ($m) => $m->hasTo($confirmo->correo) && str_starts_with($m->asuntoFinal, 'Le esperamos'));
        Mail::assertSent(CorreoEvento::class, fn ($m) => $m->hasTo($sinRespuesta->correo) && str_contains($m->mensajeFinal, 'Aún no hemos recibido su confirmación'));
        Mail::assertNotSent(CorreoEvento::class, fn ($m) => $m->hasTo($dijoQueNo->correo));
        $this->assertNotNull($this->evento->fresh()->recordatorio_enviado_at);
        $this->assertSame(2, Envio::where('motivo', 'recordatorio_auto')->count());

        // Una hora después no se repite
        $this->travelTo(Carbon::parse('2026-10-07 08:00'));
        $this->artisan('recordatorios:enviar')->assertSuccessful();
        Mail::assertSent(CorreoEvento::class, 2);
    }

    public function test_si_el_sistema_estuvo_apagado_sale_despues_pero_no_si_ya_empezo(): void
    {
        $this->invitado();

        // Se revisa el miércoles por la tarde (a las 07:00 estaba apagado): sale igual
        $this->travelTo(Carbon::parse('2026-10-07 18:30'));
        $this->artisan('recordatorios:enviar');
        Mail::assertSent(CorreoEvento::class, 1);

        // Otro evento que ya empezó: no se recuerda
        $pasado = Evento::create([
            'tipo' => Evento::REUNION, 'titulo' => 'Ya empezó', 'sede_id' => $this->sede->id, 'modalidad' => Evento::PRESENCIAL,
            'lugar' => 'Salón', 'para_todos' => true, 'inicio' => now()->subHour(), 'fin' => now()->addHour(),
        ]);
        $this->assertFalse(Evento::conRecordatorioPendiente()->contains($pasado));
    }

    public function test_no_sale_si_esta_desactivado_o_cancelado(): void
    {
        $this->invitado();
        $this->travelTo(Carbon::parse('2026-10-07 07:30'));

        $this->evento->update(['recordatorio_automatico' => false]);
        $this->artisan('recordatorios:enviar');

        $this->evento->update(['recordatorio_automatico' => true]);
        $this->evento->forceFill(['cancelado_at' => now()])->save();
        $this->artisan('recordatorios:enviar');

        Mail::assertNothingSent();
    }

    public function test_quien_fue_invitado_despues_del_momento_no_recibe_el_recordatorio(): void
    {
        $this->invitado(null, ['enviada_at' => Carbon::parse('2026-10-05 10:00')]);
        $tarde = $this->invitado(null, ['enviada_at' => Carbon::parse('2026-10-07 09:00')]);

        $this->travelTo(Carbon::parse('2026-10-07 09:30'));
        $this->artisan('recordatorios:enviar');

        Mail::assertSent(CorreoEvento::class, 1);
        Mail::assertNotSent(CorreoEvento::class, fn ($m) => $m->hasTo($tarde->correo));
    }

    public function test_al_cambiar_la_fecha_se_vuelve_a_programar(): void
    {
        $this->invitado();
        $this->travelTo(Carbon::parse('2026-10-07 07:15'));
        $this->artisan('recordatorios:enviar');
        Mail::assertSent(CorreoEvento::class, 1);

        // Se pospone una semana desde la aplicación
        $admin = User::factory()->create();
        $admin->assignRole(User::ROL_ADMINISTRADOR);
        $this->actingAs($admin)->put(route('eventos.update', ['reuniones', $this->evento]), [
            'titulo' => 'Entrega de notas', 'sede_id' => $this->sede->id, 'modalidad' => 'presencial', 'lugar' => 'Salón',
            'fecha' => '2026-10-15', 'hora_inicio' => '15:00', 'hora_fin' => '17:00', 'para_todos' => '1',
            'recordatorio_automatico' => '1', 'avisar_cambio' => '0',
        ])->assertRedirect();
        $this->assertNull($this->evento->fresh()->recordatorio_enviado_at);

        $this->travelTo(Carbon::parse('2026-10-14 07:05'));
        $this->artisan('recordatorios:enviar');
        Mail::assertSent(CorreoEvento::class, 2);
    }

    public function test_la_ficha_muestra_cuando_sale(): void
    {
        $this->invitado();
        $admin = User::factory()->create();
        $admin->assignRole(User::ROL_ADMINISTRADOR);

        $this->assertStringContainsString('miércoles 7 de octubre a las 07:00', $this->evento->estado_recordatorio);
        $this->actingAs($admin)->get(route('eventos.show', ['reuniones', $this->evento]))
            ->assertOk()->assertSee('Recordatorio:')->assertSee('Se enviará automáticamente el miércoles 7 de octubre');

        $this->artisan('recordatorios:enviar', ['--simular' => true])->expectsOutput('No hay recordatorios pendientes.');
    }
}
