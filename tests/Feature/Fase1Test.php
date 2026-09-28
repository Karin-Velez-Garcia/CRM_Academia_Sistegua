<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Fase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, RolesPermisosSeeder::class]);
    }

    private function usuario(string $rol, array $datos = []): User
    {
        $u = User::factory()->create($datos + ['password' => 'Clave1234']);
        $u->assignRole($rol);

        return $u;
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_login_correcto_registra_ultimo_acceso(): void
    {
        $u = $this->usuario('Secretaría', ['email' => 'ana@correo.com']);

        $this->post(route('login.store'), ['email' => 'ana@correo.com', 'password' => 'Clave1234'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($u);
        $this->assertNotNull($u->fresh()->ultimo_acceso_at);
    }

    public function test_usuario_inactivo_no_puede_ingresar(): void
    {
        $this->usuario('Secretaría', ['email' => 'inactivo@correo.com', 'activo' => false]);

        $this->post(route('login.store'), ['email' => 'inactivo@correo.com', 'password' => 'Clave1234'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_desactivado_con_sesion_abierta_es_expulsado(): void
    {
        $u = $this->usuario('Secretaría');
        $u->update(['activo' => false]);

        $this->actingAs($u)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_secretaria_no_puede_administrar_usuarios_ni_roles(): void
    {
        $u = $this->usuario('Secretaría');

        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertDontSee('Roles y permisos');
        $this->actingAs($u)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($u)->get(route('roles.index'))->assertForbidden();
        $this->actingAs($u)->get(route('geografia.index'))->assertOk();
    }

    public function test_administrador_crea_edita_y_elimina_usuarios(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);

        $this->actingAs($admin)->get(route('usuarios.index'))->assertOk();

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'María', 'apellidos' => 'López', 'email' => 'maria@correo.com',
            'dpi' => '1234567890101', 'telefono' => '7945-1234', 'rol' => 'Secretaría', 'activo' => '1',
            'password' => 'Clave1234', 'password_confirmation' => 'Clave1234',
        ])->assertRedirect(route('usuarios.index'));

        $maria = User::where('email', 'maria@correo.com')->firstOrFail();
        $this->assertTrue($maria->hasRole('Secretaría'));

        $this->actingAs($admin)->put(route('usuarios.update', $maria), [
            'name' => 'María José', 'apellidos' => 'López', 'email' => 'maria@correo.com',
            'rol' => 'Director de sede', 'activo' => '1',
        ])->assertRedirect(route('usuarios.index'));
        $this->assertTrue($maria->fresh()->hasRole('Director de sede'));
        $this->assertSame('María José', $maria->fresh()->name);

        $this->actingAs($admin)->delete(route('usuarios.destroy', $maria))->assertRedirect(route('usuarios.index'));
        $this->assertModelMissing($maria);
    }

    public function test_validaciones_de_usuario_en_espanol(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => '', 'apellidos' => 'X', 'email' => 'no-es-correo', 'dpi' => '123', 'telefono' => '12',
            'rol' => 'Inexistente', 'password' => 'corta', 'password_confirmation' => 'otra',
        ])->assertSessionHasErrors(['name', 'email', 'dpi', 'telefono', 'rol', 'password']);

        $errores = session('errors')->getBag('default');
        $this->assertStringContainsString('nombres', $errores->first('name'));
        $this->assertStringContainsString('8 dígitos', $errores->first('telefono'));
    }

    public function test_protecciones_del_administrador(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);

        // No puede eliminarse ni desactivarse a sí mismo
        $this->actingAs($admin)->delete(route('usuarios.destroy', $admin))->assertSessionHas('error');
        $this->actingAs($admin)->patch(route('usuarios.estado', $admin))->assertSessionHas('error');
        $this->assertModelExists($admin);
        $this->assertTrue($admin->fresh()->activo);

        // No puede quitarse el rol si es el único administrador
        $this->actingAs($admin)->put(route('usuarios.update', $admin), [
            'name' => $admin->name, 'apellidos' => 'X', 'email' => $admin->email, 'rol' => 'Secretaría', 'activo' => '1',
        ])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->hasRole(User::ROL_ADMINISTRADOR));
    }

    public function test_activar_y_desactivar_otro_usuario(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $otro = $this->usuario('Secretaría');

        $this->actingAs($admin)->patch(route('usuarios.estado', $otro))->assertSessionHas('success');
        $this->assertFalse($otro->fresh()->activo);
    }

    public function test_gestion_de_roles(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);

        $this->actingAs($admin)->get(route('roles.index'))->assertOk()->assertSee('Director de sede');

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Orientación', 'permisos' => ['contactos.ver', 'contactos.crear'],
        ])->assertRedirect(route('roles.index'));
        $rol = Role::findByName('Orientación');
        $this->assertTrue($rol->hasPermissionTo('contactos.crear'));

        $this->actingAs($admin)->get(route('roles.edit', $rol))->assertOk();

        // Rol Administrador: no se modifica ni elimina
        $adminRol = Role::findByName(User::ROL_ADMINISTRADOR);
        $this->actingAs($admin)->put(route('roles.update', $adminRol), ['name' => 'Otro'])->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('roles.destroy', $adminRol))->assertSessionHas('error');

        // Rol con usuarios asignados no se elimina
        $this->usuario('Orientación');
        $this->actingAs($admin)->delete(route('roles.destroy', $rol))->assertSessionHas('error');
        $this->assertNotNull(Role::where('name', 'Orientación')->first());
    }

    public function test_catalogo_geografico(): void
    {
        $u = $this->usuario('Secretaría');
        $elProgreso = Departamento::where('codigo', '02')->firstOrFail();

        $this->assertSame(22, Departamento::count());
        $this->actingAs($u)->get(route('geografia.index', ['buscar' => 'Sanarate']))
            ->assertOk()->assertSee('El Progreso')->assertDontSee('Quetzaltenango');
        $this->actingAs($u)->get(route('geografia.show', $elProgreso))->assertOk()->assertSee('Guastatoya')->assertSee('Sanarate');
        $this->actingAs($u)->getJson(route('api.municipios', $elProgreso))
            ->assertOk()->assertJsonCount(8)->assertJsonFragment(['codigo' => '0207', 'nombre' => 'Sanarate']);
    }

    public function test_perfil_y_cambio_de_contrasena(): void
    {
        $u = $this->usuario('Secretaría');

        $this->actingAs($u)->get(route('perfil.edit'))->assertOk();
        $this->actingAs($u)->put(route('perfil.update'), [
            'name' => 'Pedro', 'apellidos' => 'Ramírez', 'email' => $u->email, 'telefono' => '79451234',
        ])->assertSessionHas('success');
        $this->assertSame('Pedro', $u->fresh()->name);

        $this->actingAs($u)->put(route('perfil.password'), [
            'password_actual' => 'incorrecta', 'password' => 'Nueva1234', 'password_confirmation' => 'Nueva1234',
        ])->assertSessionHasErrors('password_actual');

        $this->actingAs($u)->put(route('perfil.password'), [
            'password_actual' => 'Clave1234', 'password' => 'Nueva1234', 'password_confirmation' => 'Nueva1234',
        ])->assertSessionHas('success');
    }
}
