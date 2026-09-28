<?php

namespace Tests\Feature;

use App\Mail\CorreoEvento;
use App\Models\Contacto;
use App\Models\Envio;
use App\Models\Evento;
use App\Models\Grupo;
use App\Models\Invitacion;
use App\Models\Plantilla;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\PlantillasSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\SedesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Fase4Test extends TestCase
{
    use RefreshDatabase;

    private Sede $guatemala;
    private User $admin;
    private Evento $reunion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, SedesSeeder::class, RolesPermisosSeeder::class, PlantillasSeeder::class]);
        Mail::fake();

        $this->guatemala = Sede::where('nombre', 'Ciudad de Guatemala')->firstOrFail();
        $this->guatemala->update(['direccion' => '3a. Calle 2-45, Zona 1, Ciudad de Guatemala']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(User::ROL_ADMINISTRADOR);

        $this->reunion = Evento::create([
            'tipo' => Evento::CAPACITACION, 'titulo' => 'Instalación de tabla yeso', 'sede_id' => $this->guatemala->id,
            'modalidad' => Evento::PRESENCIAL, 'lugar' => 'Salón principal', 'para_todos' => true,
            'inicio' => now()->addWeek()->setTime(15, 0), 'fin' => now()->addWeek()->setTime(17, 0),
        ]);
    }

    private function padre(array $datos = []): Contacto
    {
        return Contacto::create($datos + [
            'tipo' => Contacto::CLIENTE, 'sede_id' => $this->guatemala->id,
            'nombres' => 'María', 'apellidos' => 'García', 'correo' => uniqid().'@correo.com', 'empresa' => 'Ana',
        ]);
    }

    private function enviar(?Evento $evento = null, array $datos = [])
    {
        $evento ??= $this->reunion;

        return $this->actingAs($this->admin)->post(
            route('invitaciones.enviar', [Evento::segmentoDe($evento->tipo), $evento]),
            $datos + ['asunto' => 'Invitación: {titulo}', 'mensaje' => "Estimado(a) {nombre}:\nLe esperamos el {fecha}. Empresa: {empresa}."]
        );
    }

    public function test_enviar_invitaciones_personaliza_el_correo_y_registra_el_envio(): void
    {
        $maria = $this->padre();
        $this->padre(['correo' => null]);                         // sin correo: no se invita
        $this->padre(['acepta_correos' => false]);                // se dio de baja: no se invita
        $this->padre(['activo' => false]);                        // desactivado: no se invita

        $this->enviar()->assertSessionHas('success', fn ($m) => str_contains($m, 'Se enviaron 1 invitaciones'));

        $inv = Invitacion::sole();
        $this->assertSame($maria->id, $inv->contacto_id);
        $this->assertSame(Invitacion::ENVIADA, $inv->estado_envio);
        $this->assertNotNull($inv->enviada_at);
        $this->assertSame(1, Envio::where('motivo', 'invitacion')->value('total'));

        Mail::assertSent(CorreoEvento::class, function (CorreoEvento $mail) use ($maria, $inv) {
            $html = $mail->render();

            return $mail->hasTo($maria->correo)
                && $mail->asuntoFinal === 'Invitación: Instalación de tabla yeso'
                && str_contains($mail->mensajeFinal, 'Estimado(a) María García')
                && str_contains($mail->mensajeFinal, 'Empresa: Ana')
                && str_contains($html, $inv->url('si'))
                && str_contains($html, 'Salón principal')
                && count($mail->attachments()) === 1;
        });
    }

    public function test_no_se_repiten_invitaciones_y_se_invita_a_los_nuevos(): void
    {
        $this->padre();
        $this->enviar();
        $this->enviar()->assertSessionHas('info');               // nadie nuevo
        $this->assertSame(1, Invitacion::count());

        $this->padre();                                           // se agrega una persona
        $this->enviar()->assertSessionHas('success', fn ($m) => str_contains($m, 'Se enviaron 1 invitaciones'));
        $this->assertSame(2, Invitacion::count());
        Mail::assertSent(CorreoEvento::class, 2);
    }

    public function test_el_invitado_confirma_desde_la_pagina_publica(): void
    {
        $this->padre();
        $this->enviar();
        $inv = Invitacion::sole();

        // Abrir el enlace no responde solo; solo marca que la vio
        $this->get($inv->url('si'))->assertOk()->assertSee('Instalación de tabla yeso')->assertSee('Sí, asistiré');
        $this->assertNotNull($inv->fresh()->vista_at);
        $this->assertNull($inv->fresh()->respuesta);

        $this->post(route('invitacion.responder', $inv->token), ['respuesta' => 'confirmada', 'comentario' => 'Llegaré 10 min tarde'])
            ->assertRedirect(route('invitacion.show', $inv->token));
        $inv->refresh();
        $this->assertSame(Invitacion::CONFIRMADA, $inv->respuesta);
        $this->assertSame('invitado', $inv->respuesta_por);
        $this->assertSame('Llegaré 10 min tarde', $inv->comentario);

        // Puede cambiar de opinión
        $this->post(route('invitacion.responder', $inv->token), ['respuesta' => 'rechazada']);
        $this->assertSame(Invitacion::RECHAZADA, $inv->fresh()->respuesta);

        $this->actingAs($this->admin)->get(route('eventos.show', ['capacitaciones', $this->reunion]))
            ->assertOk()->assertSee('No asistirán');
    }

    public function test_no_se_responde_a_eventos_cancelados_o_pasados_ni_con_token_invalido(): void
    {
        $this->padre();
        $this->enviar();
        $inv = Invitacion::sole();

        $this->reunion->forceFill(['cancelado_at' => now()])->save();
        $this->get($inv->url())->assertOk()->assertSee('fue cancelada');
        $this->post(route('invitacion.responder', $inv->token), ['respuesta' => 'confirmada'])->assertSessionHas('error');
        $this->assertNull($inv->fresh()->respuesta);

        $this->get('/invitacion/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_cupo_lleno_en_capacitacion(): void
    {
        $capacitacion = Evento::create([
            'tipo' => Evento::CAPACITACION, 'titulo' => 'Taller', 'sede_id' => $this->guatemala->id, 'cupo' => 1,
            'modalidad' => Evento::VIRTUAL, 'enlace' => 'https://zoom.us/j/123', 'para_todos' => true,
            'inicio' => now()->addDays(2), 'fin' => now()->addDays(2)->addHours(2),
        ]);
        foreach (range(1, 2) as $n) {
            Contacto::create(['tipo' => Contacto::CLIENTE, 'sede_id' => $this->guatemala->id, 'nombres' => "Docente $n", 'apellidos' => 'X', 'correo' => "d$n@correo.com"]);
        }
        $this->enviar($capacitacion);
        [$primera, $segunda] = Invitacion::orderBy('id')->get()->all();

        $this->post(route('invitacion.responder', $primera->token), ['respuesta' => 'confirmada']);
        $this->post(route('invitacion.responder', $segunda->token), ['respuesta' => 'confirmada'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'cupos'));
        $this->assertSame(1, Invitacion::confirmadas()->count());
        $this->get($segunda->url())->assertSee('Ya no hay cupos');
    }

    public function test_recordatorio_solo_a_quienes_no_respondieron(): void
    {
        $this->padre();
        $this->padre();
        $this->enviar();
        $respondio = Invitacion::first();
        $respondio->update(['respuesta' => Invitacion::CONFIRMADA]);

        // Solo el grupo "sin respuesta"
        $this->actingAs($this->admin)->post(route('invitaciones.recordar', ['capacitaciones', $this->reunion]), [
            'incluir' => ['sin_respuesta'],
            'sin_respuesta' => ['asunto' => 'Recordatorio: {titulo}', 'mensaje' => 'Hola {nombres}'],
        ])->assertSessionHas('success', fn ($m) => str_contains($m, '1 sin respuesta'));

        $this->assertNull($respondio->fresh()->recordatorio_at);
        $this->assertNotNull(Invitacion::whereNull('respuesta')->sole()->recordatorio_at);
        $this->assertNotNull($this->reunion->fresh()->recordatorio_enviado_at);
        Mail::assertSent(CorreoEvento::class, fn ($m) => $m->motivo === 'recordatorio' && $m->asuntoFinal === 'Recordatorio: Instalación de tabla yeso');
        Mail::assertSent(CorreoEvento::class, 3); // 2 invitaciones + 1 recordatorio

        // Los dos grupos, con los textos por defecto si se dejan vacíos
        $this->actingAs($this->admin)->post(route('invitaciones.recordar', ['capacitaciones', $this->reunion]), [
            'incluir' => ['sin_respuesta', 'confirmados'],
            'confirmados' => ['asunto' => '', 'mensaje' => ''],
        ])->assertSessionHas('success', fn ($m) => str_contains($m, '1 sin respuesta y 1 que confirmaron'));
        Mail::assertSent(CorreoEvento::class, fn ($m) => $m->hasTo($respondio->correo) && str_starts_with($m->asuntoFinal, 'Le esperamos'));

        // Sin elegir grupo
        $this->actingAs($this->admin)->post(route('invitaciones.recordar', ['capacitaciones', $this->reunion]), [])
            ->assertSessionHasErrors('incluir');
    }

    public function test_aviso_de_cancelacion_y_de_cambio(): void
    {
        $this->padre();
        $noVa = $this->padre();
        $this->enviar();
        Invitacion::where('contacto_id', $noVa->id)->update(['respuesta' => Invitacion::RECHAZADA]);

        // Cambio de hora con aviso: solo a quien no dijo que no
        $this->actingAs($this->admin)->put(route('eventos.update', ['capacitaciones', $this->reunion]), [
            'titulo' => 'Instalación de tabla yeso', 'sede_id' => $this->guatemala->id, 'modalidad' => 'presencial', 'lugar' => 'Salón principal',
            'fecha' => $this->reunion->inicio->format('Y-m-d'), 'hora_inicio' => '16:00', 'hora_fin' => '18:00',
            'para_todos' => '1',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'a 1 invitados'));
        Mail::assertSent(CorreoEvento::class, fn ($m) => $m->motivo === 'cambio');

        // Solo cambiar el título no avisa
        $this->actingAs($this->admin)->put(route('eventos.update', ['capacitaciones', $this->reunion]), [
            'titulo' => 'Instalación de tabla yeso (módulo 2)', 'sede_id' => $this->guatemala->id, 'modalidad' => 'presencial', 'lugar' => 'Salón principal',
            'fecha' => $this->reunion->inicio->format('Y-m-d'), 'hora_inicio' => '16:00', 'hora_fin' => '18:00',
            'para_todos' => '1',
        ]);
        $this->assertSame(1, Envio::where('motivo', 'cambio')->count());

        $this->actingAs($this->admin)->patch(route('eventos.cancelar', ['capacitaciones', $this->reunion]), ['motivo' => 'Feriado'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Se avisó por correo a 1'));
        Mail::assertSent(CorreoEvento::class, fn ($m) => $m->motivo === 'cancelacion'
            && str_contains($m->mensajeFinal, 'Motivo: Feriado')
            && ! str_contains($m->render(), 'Confirmo asistencia'));
    }

    public function test_con_invitaciones_enviadas_no_se_elimina_y_anular_siempre_avisa(): void
    {
        $this->padre();
        $this->enviar();

        $this->actingAs($this->admin)->get(route('eventos.show', ['capacitaciones', $this->reunion]))
            ->assertOk()->assertSee('no se puede eliminar, solo anular o posponer')->assertSee('Posponer');
        $this->actingAs($this->admin)->delete(route('eventos.destroy', ['capacitaciones', $this->reunion]))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No se puede eliminar'));
        $this->assertModelExists($this->reunion);

        // Aunque se intente desactivar el aviso, se envía igual
        $this->actingAs($this->admin)->patch(route('eventos.cancelar', ['capacitaciones', $this->reunion]), ['avisar' => '0'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'anulada') && str_contains($m, 'a 1 invitados'));
        Mail::assertSent(CorreoEvento::class, fn ($m) => $m->motivo === 'cancelacion');
        $this->assertSame('cancelado', $this->reunion->fresh()->estado);
    }

    public function test_sin_invitaciones_enviadas_se_puede_eliminar(): void
    {
        $this->padre();

        $this->actingAs($this->admin)->delete(route('eventos.destroy', ['capacitaciones', $this->reunion]))
            ->assertRedirect(route('eventos.index', 'capacitaciones'));
        $this->assertModelMissing($this->reunion);
    }

    public function test_posponer_cambia_la_fecha_y_avisa_la_nueva(): void
    {
        $this->padre();
        $this->enviar();
        $anterior = $this->reunion->inicio->copy();
        $nueva = now()->addWeeks(3)->format('Y-m-d');
        $this->reunion->forceFill(['recordatorio_enviado_at' => now()])->save();

        $this->actingAs($this->admin)->patch(route('eventos.posponer', ['capacitaciones', $this->reunion]), [
            'fecha' => $nueva, 'hora_inicio' => '09:00', 'hora_fin' => '11:00', 'motivo' => 'Feriado nacional',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'pospuesta') && str_contains($m, 'a 1 invitados'));

        $evento = $this->reunion->fresh();
        $this->assertSame("{$nueva} 09:00", $evento->inicio->format('Y-m-d H:i'));
        $this->assertSame('11:00', $evento->fin->format('H:i'));
        $this->assertNull($evento->recordatorio_enviado_at);
        $this->assertSame(1, Envio::where('motivo', 'posposicion')->count());
        Mail::assertSent(CorreoEvento::class, fn ($m) => $m->motivo === 'posposicion'
            && str_contains($m->mensajeFinal, 'fue pospuesta')
            && str_contains($m->mensajeFinal, 'Fecha anterior: '.$anterior->translatedFormat('l j \d\e F'))
            && str_contains($m->mensajeFinal, 'Motivo: Feriado nacional')
            && str_contains($m->render(), 'Confirmo asistencia'));
    }

    public function test_posponer_valida_la_nueva_fecha(): void
    {
        $ruta = route('eventos.posponer', ['capacitaciones', $this->reunion]);

        $this->actingAs($this->admin)->patch($ruta, ['fecha' => now()->subDay()->format('Y-m-d'), 'hora_inicio' => '09:00', 'hora_fin' => '10:00'])
            ->assertSessionHasErrorsIn('posponer', 'fecha');
        $this->actingAs($this->admin)->patch($ruta, ['fecha' => now()->addWeek()->format('Y-m-d'), 'hora_inicio' => '10:00', 'hora_fin' => '09:00'])
            ->assertSessionHasErrorsIn('posponer', 'hora_fin');
        $this->actingAs($this->admin)->patch($ruta, [
            'fecha' => $this->reunion->inicio->format('Y-m-d'), 'hora_inicio' => $this->reunion->inicio->format('H:i'), 'hora_fin' => $this->reunion->fin->format('H:i'),
        ])->assertSessionHasErrorsIn('posponer', 'fecha');

        // Una anulada no se pospone
        $this->reunion->forceFill(['cancelado_at' => now()])->save();
        $this->actingAs($this->admin)->patch($ruta, ['fecha' => now()->addWeek()->format('Y-m-d'), 'hora_inicio' => '09:00', 'hora_fin' => '10:00'])
            ->assertNotFound();
    }

    public function test_respuesta_manual_y_reenvio(): void
    {
        $p = $this->padre();
        $this->enviar();
        $inv = Invitacion::sole();

        $this->actingAs($this->admin)->patch(route('invitaciones.responder', ['capacitaciones', $this->reunion, $inv]), ['respuesta' => 'confirmada'])
            ->assertSessionHas('success');
        $this->assertSame('personal', $inv->fresh()->respuesta_por);

        $this->actingAs($this->admin)->patch(route('invitaciones.responder', ['capacitaciones', $this->reunion, $inv]), ['respuesta' => '']);
        $this->assertNull($inv->fresh()->respuesta);

        // Corrige el correo y reenvía
        $p->update(['correo' => 'nuevo@correo.com']);
        $this->actingAs($this->admin)->post(route('invitaciones.reenviar', ['capacitaciones', $this->reunion, $inv]))->assertSessionHas('success');
        $this->assertSame('nuevo@correo.com', $inv->fresh()->correo);
        Mail::assertSent(CorreoEvento::class, fn ($m) => $m->hasTo('nuevo@correo.com'));
    }

    public function test_error_de_envio_queda_registrado(): void
    {
        $this->padre();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Buzón no existe'));

        $this->enviar();

        $inv = Invitacion::sole();
        $this->assertSame(Invitacion::FALLIDA, $inv->estado_envio);
        $this->assertStringContainsString('Buzón no existe', $inv->error);
    }

    public function test_darse_de_baja(): void
    {
        $p = $this->padre();
        $this->enviar();
        $inv = Invitacion::sole();

        $this->get(route('invitacion.baja', $inv->token))->assertOk()->assertSee('¿Dejar de recibir correos?');
        $this->post(route('invitacion.baja.store', $inv->token))->assertRedirect();
        $this->assertFalse($p->fresh()->acepta_correos);
        $this->get(route('invitacion.baja', $inv->token))->assertSee('ya no recibirá más correos');
    }

    public function test_vista_previa_plantillas_y_permisos(): void
    {
        $this->padre(['nombres' => 'Rosa', 'apellidos' => 'Morales']);

        $this->actingAs($this->admin)->get(route('invitaciones.vista-previa', ['capacitaciones', $this->reunion, 'mensaje' => 'Hola {nombre}']))
            ->assertOk()->assertSee('Hola Rosa Morales')->assertSee('Confirmo asistencia');

        // Plantillas: solo una predeterminada por tipo; la predeterminada no se puede desactivar
        $this->actingAs($this->admin)->post(route('plantillas.store'), [
            'nombre' => 'Otra', 'tipo_evento' => 'capacitacion', 'asunto' => 'A', 'mensaje' => 'M', 'predeterminada' => '1',
        ])->assertRedirect(route('plantillas.index'));
        $this->assertSame(1, Plantilla::where('tipo_evento', 'capacitacion')->where('predeterminada', true)->count());
        $this->assertSame('Otra', Plantilla::predeterminadaPara('capacitacion')->nombre);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('plantillas.destroy'));
        $otra = Plantilla::where('nombre', 'Otra')->sole();
        $this->actingAs($this->admin)->patch(route('plantillas.estado', $otra))->assertSessionHas('error');
        $this->assertTrue($otra->fresh()->activa);

        // Una no predeterminada se desactiva y ya no se ofrece en el evento
        $vieja = Plantilla::where('nombre', '!=', 'Otra')->first();
        $this->actingAs($this->admin)->patch(route('plantillas.estado', $vieja))->assertSessionHas('success');
        $this->assertFalse($vieja->fresh()->activa);
        $this->actingAs($this->admin)->get(route('dashboard')); // consume el mensaje de confirmación
        $this->actingAs($this->admin)->get(route('eventos.show', ['capacitaciones', $this->reunion]))->assertOk()->assertDontSee($vieja->nombre);

        // Director de otra sede no ve ni envía
        $director = User::factory()->create(['sede_id' => Sede::where('nombre', 'Chiquimula')->value('id')]);
        $director->assignRole('Director de sede');
        $this->actingAs($director)->post(route('invitaciones.enviar', ['capacitaciones', $this->reunion]), ['asunto' => 'A', 'mensaje' => 'M'])->assertForbidden();

        $this->actingAs($this->admin)->get(route('invitaciones.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('plantillas.index'))->assertOk()->assertSee('Otra');
    }

    public function test_grupos_de_otra_sede_no_reciben(): void
    {
        $grupo = Grupo::create(['nombre' => 'Todos los comités', 'tipo' => Contacto::CLIENTE, 'sede_id' => null]);
        $this->reunion->update(['para_todos' => false]);
        $this->reunion->grupos()->sync([$grupo->id]);
        $local = $this->padre();
        $otra = $this->padre(['sede_id' => Sede::where('nombre', 'Chiquimula')->value('id')]);
        $grupo->contactos()->attach([$local->id, $otra->id]);

        $this->enviar();

        $this->assertSame([$local->id], Invitacion::pluck('contacto_id')->all());
    }
}
