<?php

namespace Tests\Feature;

use App\Mail\CorreoEvento;
use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Sede;
use App\Models\User;
use App\Services\InvitacionesEvento;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\PlantillasSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\SedesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QrAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private Sede $sede;
    private User $admin;
    private Evento $evento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, SedesSeeder::class, RolesPermisosSeeder::class, PlantillasSeeder::class]);
        Mail::fake();
        $this->sede = Sede::where('nombre', 'Sanarate')->firstOrFail();
        $this->admin = User::factory()->create();
        $this->admin->assignRole(User::ROL_ADMINISTRADOR);
        // Empieza en 30 minutos: el registro por QR ya está abierto (abre 60 minutos antes)
        $this->evento = Evento::create([
            'tipo' => Evento::REUNION, 'titulo' => 'Reunión de padres', 'sede_id' => $this->sede->id,
            'modalidad' => Evento::PRESENCIAL, 'lugar' => 'Salón', 'para_todos' => true,
            'inicio' => now()->addMinutes(30), 'fin' => now()->addMinutes(150),
        ]);
    }

    private function contacto(array $datos = []): Contacto
    {
        return Contacto::create($datos + [
            'tipo' => Contacto::PADRE, 'sede_id' => $this->sede->id, 'nombres' => 'N'.uniqid(), 'apellidos' => 'A', 'correo' => uniqid().'@correo.com',
        ]);
    }

    public function test_cada_evento_tiene_su_qr_y_se_puede_renovar(): void
    {
        $this->assertNotEmpty($this->evento->token_registro);

        $this->actingAs($this->admin)->get(route('asistencia.qr', ['reuniones', $this->evento]))
            ->assertOk()->assertSee('data:image/png;base64', false)->assertSee('Abierto ahora');
        $png = $this->actingAs($this->admin)->get(route('asistencia.qr.png', ['reuniones', $this->evento]));
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $png->getContent());

        $anterior = $this->evento->token_registro;
        $this->actingAs($this->admin)->post(route('asistencia.qr.renovar', ['reuniones', $this->evento]))->assertSessionHas('success');
        $this->assertNotSame($anterior, $this->evento->fresh()->token_registro);
        $this->get(route('registro.show', $anterior))->assertNotFound();
    }

    public function test_el_asistente_se_registra_con_correo_o_dpi(): void
    {
        $maria = $this->contacto(['nombres' => 'María', 'apellidos' => 'García', 'correo' => 'maria@correo.com']);
        $luis = $this->contacto(['nombres' => 'Luis', 'correo' => null, 'dpi' => '2584736910207']);
        $url = route('registro.registrar', $this->evento->token_registro);

        $this->get(route('registro.show', $this->evento->token_registro))->assertOk()->assertSee('Registrar mi asistencia');

        $this->post($url, ['identificador' => 'MARIA@correo.com '])->assertRedirect();
        $this->get(route('registro.show', $this->evento->token_registro))->assertSee('¡Asistencia registrada!')->assertSee('María García');
        $inv = Invitacion::where('contacto_id', $maria->id)->sole();
        $this->assertTrue($inv->asistio);
        $this->assertSame('qr_evento', $inv->asistencia_metodo);
        $this->assertNull($inv->asistencia_por);

        // DPI con espacios
        $this->post($url, ['identificador' => '2584 73691 0207']);
        $this->assertTrue(Invitacion::where('contacto_id', $luis->id)->value('asistio'));

        // Registrarse dos veces
        $this->post($url, ['identificador' => 'maria@correo.com']);
        $this->get(route('registro.show', $this->evento->token_registro))->assertSee('Ya estaba registrado(a)');

        // No encontrado u otra sede
        $otra = $this->contacto(['correo' => 'otra@correo.com', 'sede_id' => Sede::where('nombre', 'Cobán')->value('id')]);
        $this->post($url, ['identificador' => 'otra@correo.com'])->assertSessionHas('error', fn ($m) => str_contains($m, 'mesa de registro'));
        $this->post($url, ['identificador' => 'nadie@correo.com'])->assertSessionHas('error');
        $this->assertNull(Invitacion::where('contacto_id', $otra->id)->first());
    }

    public function test_el_qr_del_evento_solo_funciona_en_su_horario(): void
    {
        $c = $this->contacto(['correo' => 'a@correo.com']);
        $url = route('registro.registrar', $this->evento->token_registro);

        $this->travel(-2)->hours(); // faltan 2 h 30 min: todavía cerrado
        $this->get(route('registro.show', $this->evento->token_registro))->assertSee('El registro se abre');
        $this->post($url, ['identificador' => 'a@correo.com'])->assertSessionHas('error');

        $this->travelBack();
        $this->travel(4)->hours(); // ya terminó hace más de 30 min
        $this->post($url, ['identificador' => 'a@correo.com'])->assertSessionHas('error');
        $this->assertNull(Invitacion::where('contacto_id', $c->id)->first());
    }

    public function test_qr_personal_en_la_invitacion_y_escaneo_del_personal(): void
    {
        $c = $this->contacto(['nombres' => 'Rosa', 'apellidos' => 'Morales']);
        (new InvitacionesEvento($this->evento))->invitar('Invitación', 'Hola {nombre}', $this->admin);
        $inv = Invitacion::sole();

        // El correo y la página del invitado llevan el pase de entrada
        Mail::assertSent(CorreoEvento::class, fn ($m) => str_contains($m->render(), 'Su pase de entrada'));
        $this->get($inv->url())->assertOk()->assertSee('Su pase de entrada');

        // Sin sesión: pide iniciar sesión
        $this->get($inv->urlEscaneo())->assertRedirect(route('login'));

        // El personal escanea
        $this->actingAs($this->admin)->get($inv->urlEscaneo())->assertOk()->assertSee('Asistencia registrada')->assertSee('Rosa Morales');
        $inv->refresh();
        $this->assertTrue($inv->asistio);
        $this->assertSame('qr_personal', $inv->asistencia_metodo);
        $this->assertSame($this->admin->id, $inv->asistencia_por);

        $this->actingAs($this->admin)->get($inv->urlEscaneo())->assertSee('Ya estaba registrado(a)');

        // Secretaría de otra sede no puede
        $otro = User::factory()->create(['sede_id' => Sede::where('nombre', 'Cobán')->value('id')]);
        $otro->assignRole('Secretaría');
        $this->actingAs($otro)->get($inv->urlEscaneo())->assertForbidden();
    }

    public function test_escaneo_fuera_de_horario_no_marca(): void
    {
        $this->contacto();
        (new InvitacionesEvento($this->evento))->invitar('A', 'M', $this->admin);
        $inv = Invitacion::sole();

        $this->travel(-3)->hours();
        $this->actingAs($this->admin)->get($inv->urlEscaneo())->assertOk()->assertSee('Todavía no se puede registrar');
        $this->assertNull($inv->fresh()->asistio);
    }

    public function test_panel_de_control_compara_enviadas_confirmadas_asistencias(): void
    {
        $evento = $this->evento;
        $crear = function (?string $respuesta, ?bool $asistio, string $envio = Invitacion::ENVIADA, string $metodo = 'manual') use ($evento) {
            $c = $this->contacto();
            Invitacion::create(['evento_id' => $evento->id, 'contacto_id' => $c->id, 'correo' => $c->correo,
                'estado_envio' => $envio, 'respuesta' => $respuesta, 'asistio' => $asistio, 'asistencia_metodo' => $asistio ? $metodo : null]);
        };
        $crear('confirmada', true, metodo: 'qr_evento');   // confirmó y asistió
        $crear('confirmada', true, metodo: 'qr_personal'); // confirmó y asistió
        $crear('confirmada', false);                       // confirmó y faltó
        $crear(null, true);                                // no confirmó pero asistió
        $crear(null, null);                                // sin respuesta, no llegó
        $crear('rechazada', null);                         // dijo que no
        $crear(null, true, Invitacion::NO_ENVIADA);        // llegó sin invitación

        $c = $evento->control();
        $this->assertSame(6, $c['enviadas']);
        $this->assertSame(3, $c['confirmadas']);
        $this->assertSame(3, $c['no_confirmadas']);
        $this->assertSame(1, $c['rechazadas']);
        $this->assertSame(2, $c['sin_respuesta']);
        $this->assertSame(4, $c['asistencias']);
        $this->assertSame(3, $c['inasistencias']);           // 6 invitados − 3 invitados que asistieron
        $this->assertSame(2, $c['confirmaron_y_asistieron']);
        $this->assertSame(1, $c['confirmaron_y_faltaron']);
        $this->assertSame(1, $c['no_confirmaron_y_asistieron']);
        $this->assertSame(1, $c['sin_invitacion_asistieron']);
        $this->assertSame(['manual' => 2, 'qr_evento' => 1, 'qr_personal' => 1], $c['por_metodo']);

        $this->actingAs($this->admin)->get(route('eventos.show', ['reuniones', $evento]))
            ->assertOk()->assertSee('Control del evento')->assertSee('Inasistencias')->assertSee('Confirmaron y faltaron');
    }

    public function test_boton_de_recordatorio_en_la_ficha(): void
    {
        $this->contacto();
        (new InvitacionesEvento($this->evento))->invitar('A', 'M', $this->admin);

        $this->actingAs($this->admin)->get(route('eventos.show', ['reuniones', $this->evento]))
            ->assertOk()->assertSee('Enviar recordatorio')->assertSee('Aún no se ha enviado');
    }
}
