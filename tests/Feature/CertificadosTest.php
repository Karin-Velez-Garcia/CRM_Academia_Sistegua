<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\PlantillaCertificado;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\CertificadosSeeder;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\SedesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificadosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, SedesSeeder::class, RolesPermisosSeeder::class, CertificadosSeeder::class]);
        $this->sede = Sede::where('nombre', 'Ciudad de Guatemala')->firstOrFail();
        $this->admin = User::factory()->create();
        $this->admin->assignRole(User::ROL_ADMINISTRADOR);
    }

    private function capacitacionConAsistente(array $datos = []): array
    {
        $evento = Evento::create($datos + [
            'tipo' => Evento::CAPACITACION, 'titulo' => 'Cielo falso suspendido', 'sede_id' => $this->sede->id,
            'modalidad' => Evento::PRESENCIAL, 'lugar' => 'Centro de capacitación', 'para_todos' => true,
            'inicio' => now()->subDay(), 'fin' => now()->subDay()->addHours(4), 'facilitador' => 'Ing. Mario Cabrera',
        ]);
        $contacto = Contacto::create([
            'tipo' => Contacto::CLIENTE, 'sede_id' => $this->sede->id, 'nombres' => 'Rosa', 'apellidos' => 'Morales',
            'correo' => 'rosa@correo.com', 'empresa' => 'Constructora Morales', 'oficio' => 'Contratista',
        ]);
        $inv = Invitacion::create([
            'evento_id' => $evento->id, 'contacto_id' => $contacto->id, 'correo' => $contacto->correo,
            'estado_envio' => Invitacion::ENVIADA, 'asistio' => true,
        ]);

        return [$evento, $inv];
    }

    public function test_existe_un_diseno_predeterminado_con_sus_elementos(): void
    {
        $plantilla = PlantillaCertificado::predeterminada();

        $this->assertNotNull($plantilla);
        $this->assertTrue($plantilla->predeterminada);
        $this->assertNotEmpty($plantilla->elementos);
        $this->assertContains('texto', array_column($plantilla->elementos, 'tipo'));
    }

    public function test_crear_editar_y_guardar_un_diseno(): void
    {
        $this->actingAs($this->admin)->post(route('certificados.create'))->assertRedirect();
        $nueva = PlantillaCertificado::latest('id')->first();
        $this->assertSame('Diseño nuevo', $nueva->nombre);

        $this->actingAs($this->admin)->get(route('certificados.edit', $nueva))->assertOk()->assertSee('Editor de certificado');

        $this->actingAs($this->admin)->put(route('certificados.update', $nueva), [
            'nombre' => 'Certificado premium',
            'orientacion' => 'vertical',
            'elementos' => json_encode([
                ['id' => 'a', 'tipo' => 'texto', 'texto' => 'Hola {nombre}', 'x' => 10, 'y' => 20, 'ancho' => 80,
                    'fuente' => 18, 'color' => '#112233', 'alineacion' => 'center', 'negrita' => true],
                ['id' => 'b', 'tipo' => 'linea', 'x' => 10, 'y' => 40, 'ancho' => 80, 'color' => '#AABBCC', 'grosor' => 2],
            ]),
        ])->assertRedirect();

        $nueva->refresh();
        $this->assertSame('Certificado premium', $nueva->nombre);
        $this->assertSame('vertical', $nueva->orientacion);
        $this->assertCount(2, $nueva->elementos);
        $this->assertSame('Hola {nombre}', $nueva->elementos[0]['texto']);
        $this->assertTrue($nueva->elementos[0]['negrita']);
    }

    public function test_el_diseno_se_limpia_de_valores_invalidos(): void
    {
        $plantilla = PlantillaCertificado::predeterminada();

        $this->actingAs($this->admin)->put(route('certificados.update', $plantilla), [
            'nombre' => 'Saneado', 'orientacion' => 'horizontal',
            'elementos' => json_encode([
                ['tipo' => 'script', 'x' => 1, 'y' => 1, 'ancho' => 1],                       // tipo inválido: se descarta
                ['id' => 'x!!', 'tipo' => 'texto', 'texto' => '<b>Hola</b><script>alert(1)</script>',
                    'x' => 999, 'y' => -999, 'ancho' => 500, 'fuente' => 500, 'color' => 'javascript:x', 'alineacion' => 'raro'],
            ]),
        ])->assertRedirect();

        $elementos = $plantilla->refresh()->elementos;
        $this->assertCount(1, $elementos);
        $this->assertSame('x', $elementos[0]['id']);
        $this->assertSame('Holaalert(1)', $elementos[0]['texto']);   // sin etiquetas HTML
        $this->assertEquals(110, $elementos[0]['x']);                 // recortado al máximo
        $this->assertEquals(-10, $elementos[0]['y']);                 // recortado al mínimo
        $this->assertSame(72, $elementos[0]['fuente']);
        $this->assertSame('#252F4A', $elementos[0]['color']);         // color inválido -> por defecto
        $this->assertSame('center', $elementos[0]['alineacion']);
    }

    public function test_predeterminada_duplicar_y_no_borrar_la_ultima(): void
    {
        $original = PlantillaCertificado::predeterminada();

        $this->actingAs($this->admin)->post(route('certificados.duplicar', $original))->assertRedirect();
        $copia = PlantillaCertificado::latest('id')->first();
        $this->assertSame('Copia de '.$original->nombre, $copia->nombre);
        $this->assertFalse($copia->predeterminada);
        $this->assertSame($original->elementos, $copia->elementos);

        $this->actingAs($this->admin)->patch(route('certificados.predeterminada', $copia))->assertRedirect();
        $this->assertTrue($copia->refresh()->predeterminada);
        $this->assertFalse($original->refresh()->predeterminada);

        $this->actingAs($this->admin)->delete(route('certificados.destroy', $original))->assertRedirect();
        $this->assertModelMissing($original);

        // La última que queda no se puede borrar
        $this->actingAs($this->admin)->delete(route('certificados.destroy', $copia))->assertSessionHas('error');
        $this->assertModelExists($copia);
    }

    public function test_la_constancia_usa_el_diseno_de_la_capacitacion(): void
    {
        $propio = PlantillaCertificado::create([
            'nombre' => 'Solo para este curso', 'orientacion' => 'vertical',
            'elementos' => [['id' => 'a', 'tipo' => 'texto', 'texto' => 'Curso: {curso}', 'x' => 10, 'y' => 20,
                'ancho' => 80, 'fuente' => 14, 'color' => '#000000', 'alineacion' => 'center']],
        ]);
        [$evento, $inv] = $this->capacitacionConAsistente(['plantilla_certificado_id' => $propio->id]);

        $resp = $this->actingAs($this->admin)->get(route('constancias.descargar', [$evento, $inv]));
        $resp->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $resp->getContent());

        // Al generarse, la constancia recibe su código de verificación
        $this->assertNotNull($inv->refresh()->codigo_constancia);
    }

    public function test_sin_diseno_propio_se_usa_el_predeterminado(): void
    {
        [$evento, $inv] = $this->capacitacionConAsistente();
        $this->assertNull($evento->plantilla_certificado_id);

        $this->actingAs($this->admin)->get(route('constancias.descargar', [$evento, $inv]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_vista_previa_en_pdf(): void
    {
        $this->actingAs($this->admin)->get(route('certificados.vista-previa', PlantillaCertificado::predeterminada()))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_subir_imagen_para_el_diseno(): void
    {
        Storage::fake('local');

        $resp = $this->actingAs($this->admin)->post(route('certificados.imagen'), [
            'imagen' => UploadedFile::fake()->image('firma.png', 300, 120),
        ]);

        $resp->assertOk()->assertJsonStructure(['archivo', 'url']);
        Storage::assertExists('certificados/'.$resp->json('archivo'));

        // Un archivo que no es imagen se rechaza
        $this->actingAs($this->admin)->post(route('certificados.imagen'), [
            'imagen' => UploadedFile::fake()->create('virus.php', 10, 'text/php'),
        ])->assertSessionHasErrors('imagen');
    }

    public function test_secretaria_solo_consulta_los_disenos(): void
    {
        $secretaria = User::factory()->create();
        $secretaria->assignRole('Secretaría');
        $plantilla = PlantillaCertificado::predeterminada();

        $this->actingAs($secretaria)->get(route('certificados.index'))->assertOk();
        $this->actingAs($secretaria)->get(route('certificados.edit', $plantilla))->assertForbidden();
        $this->actingAs($secretaria)->post(route('certificados.create'))->assertForbidden();
        $this->actingAs($secretaria)->delete(route('certificados.destroy', $plantilla))->assertForbidden();
    }
}
