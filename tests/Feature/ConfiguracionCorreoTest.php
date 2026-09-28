<?php

namespace Tests\Feature;

use App\Models\ConfiguracionCorreo;
use App\Models\User;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfiguracionCorreoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, RolesPermisosSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(User::ROL_ADMINISTRADOR);
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'modo' => 'smtp',
            'host' => 'smtp.gmail.com',
            'puerto' => 587,
            'cifrado' => 'tls',
            'usuario' => 'envios@gmail.com',
            'password' => 'abcd efgh ijkl mnop',
            'remitente_correo' => 'envios@gmail.com',
            'remitente_nombre' => 'Academia Sistegua',
        ];
    }

    public function test_secretaria_no_puede_ver_ni_cambiar_el_correo(): void
    {
        $u = User::factory()->create();
        $u->assignRole('Secretaría');

        $this->actingAs($u)->get(route('dashboard'))->assertDontSee('Correo de envío');
        $this->actingAs($u)->get(route('correo.edit'))->assertForbidden();
        $this->actingAs($u)->put(route('correo.update'), $this->datos())->assertForbidden();
    }

    public function test_guarda_la_configuracion_con_la_contrasena_cifrada(): void
    {
        $this->actingAs($this->admin)->get(route('correo.edit'))->assertOk()->assertSee('Cuenta de envío');

        $this->actingAs($this->admin)->put(route('correo.update'), $this->datos())
            ->assertRedirect(route('correo.edit'))->assertSessionHasNoErrors();

        $config = ConfiguracionCorreo::first();
        $this->assertSame('abcdefghijklmnop', $config->password); // Gmail: sin espacios
        $this->assertNotSame('abcdefghijklmnop', DB::table('configuracion_correo')->value('password'));
        $this->assertSame($this->admin->id, $config->actualizado_por);
    }

    public function test_contrasena_en_blanco_conserva_la_anterior(): void
    {
        $this->actingAs($this->admin)->put(route('correo.update'), $this->datos());
        $this->actingAs($this->admin)->put(route('correo.update'), $this->datos(['password' => '', 'remitente_nombre' => 'Otra']));

        $config = ConfiguracionCorreo::first();
        $this->assertSame('abcdefghijklmnop', $config->password);
        $this->assertSame('Otra', $config->remitente_nombre);
        $this->assertSame(1, ConfiguracionCorreo::count());
    }

    public function test_smtp_exige_servidor_y_puerto(): void
    {
        $this->actingAs($this->admin)->put(route('correo.update'), $this->datos(['host' => '', 'puerto' => '']))
            ->assertSessionHasErrors(['host', 'puerto']);

        $this->actingAs($this->admin)->put(route('correo.update'), $this->datos(['modo' => 'log', 'host' => '', 'puerto' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_la_configuracion_guardada_reemplaza_al_env(): void
    {
        ConfiguracionCorreo::create($this->datos(['cifrado' => 'ssl', 'puerto' => 465, 'remitente_correo' => 'otro@gmail.com']));
        app()->forgetInstance('mail.manager');

        app('mail.manager');

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('otro@gmail.com', config('mail.from.address'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
    }

    public function test_correo_de_prueba_en_modo_de_prueba(): void
    {
        $this->actingAs($this->admin)->put(route('correo.update'), $this->datos(['modo' => 'log']));

        $this->actingAs($this->admin)->post(route('correo.probar'), ['destino' => 'yo@correo.com'])
            ->assertSessionHas('success');
    }
}
