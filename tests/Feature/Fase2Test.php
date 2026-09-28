<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\Grupo;
use App\Models\Sede;
use App\Models\User;
use App\Services\ImportadorContactos;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\SedesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Fase2Test extends TestCase
{
    use RefreshDatabase;

    private Sede $guatemala;
    private Sede $xela;
    private Sede $chiquimula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, SedesSeeder::class, RolesPermisosSeeder::class]);
        $this->guatemala = Sede::where('nombre', 'Ciudad de Guatemala')->firstOrFail();
        $this->xela = Sede::where('nombre', 'Quetzaltenango')->firstOrFail();
        $this->chiquimula = Sede::where('nombre', 'Chiquimula')->firstOrFail();
    }

    private function usuario(string $rol, ?Sede $sede = null): User
    {
        $u = User::factory()->create(['sede_id' => $sede?->id]);
        $u->assignRole($rol);

        return $u;
    }

    private function padre(array $datos = []): Contacto
    {
        return Contacto::create($datos + [
            'tipo' => Contacto::CLIENTE, 'sede_id' => $this->guatemala->id,
            'nombres' => 'Juan', 'apellidos' => 'Pérez', 'correo' => 'juan'.uniqid().'@correo.com',
        ]);
    }

    private function excel(array $filas): UploadedFile
    {
        $libro = new Spreadsheet();
        $libro->getActiveSheet()->fromArray($filas);
        $ruta = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return new UploadedFile($ruta, 'contactos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_sedes_de_la_academia_estan_cargadas(): void
    {
        $this->assertSame(['Chiquimula', 'Ciudad de Guatemala', 'Quetzaltenango'], Sede::orderBy('nombre')->pluck('nombre')->all());
        $this->assertSame('Guatemala', $this->guatemala->municipio->departamento->nombre);
        $this->assertSame('Quetzaltenango', $this->xela->municipio->departamento->nombre);
        $this->assertSame('Chiquimula', $this->chiquimula->municipio->departamento->nombre);
    }

    public function test_crud_de_sedes_y_desactivar(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);

        $this->actingAs($admin)->get(route('sedes.index'))->assertOk()->assertSee('Sede Ciudad de Guatemala');
        $this->actingAs($admin)->post(route('sedes.store'), [
            'nombre' => 'Mixco', 'municipio_id' => \App\Models\Municipio::where('codigo', '0108')->value('id'), 'activa' => '1', 'telefono' => '79451234',
            'direccion' => 'Calzada Roosevelt, Mixco',
        ])->assertRedirect(route('sedes.index'));
        $this->assertDatabaseHas('sedes', ['nombre' => 'Mixco']);

        // Las sedes no se eliminan: al desactivarla ya no se ofrece al registrar clientes
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('sedes.destroy'));
        $this->actingAs($admin)->patch(route('sedes.estado', $this->xela))->assertSessionHas('success');
        $this->assertFalse($this->xela->fresh()->activa);
        $this->actingAs($admin)->post(route('contactos.store', 'clientes'), [
            'nombres' => 'X', 'apellidos' => 'Y', 'sede_id' => $this->xela->id,
        ])->assertSessionHasErrors('sede_id');
        $this->actingAs($admin)->patch(route('sedes.estado', $this->xela));
        $this->assertTrue($this->xela->fresh()->activa);
    }

    public function test_crear_editar_y_desactivar_cliente(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $grupo = Grupo::create(['nombre' => 'Instaladores certificados', 'tipo' => Contacto::CLIENTE, 'sede_id' => $this->guatemala->id]);

        $this->actingAs($admin)->get(route('contactos.create', 'clientes'))->assertOk()->assertSee('Empresa o negocio');

        $this->actingAs($admin)->post(route('contactos.store', 'clientes'), [
            'nombres' => 'María', 'apellidos' => 'García', 'correo' => 'MARIA@Correo.com', 'telefono' => '58743210',
            'dpi' => '2584736910207', 'sede_id' => $this->guatemala->id, 'empresa' => 'Constructora García',
            'oficio' => 'Contratista', 'acepta_correos' => '1', 'grupos' => [$grupo->id],
        ])->assertRedirect(route('contactos.index', 'clientes'));

        $maria = Contacto::where('correo', 'maria@correo.com')->firstOrFail();
        $this->assertSame(Contacto::CLIENTE, $maria->tipo);
        $this->assertSame('5874-3210', $maria->telefono);
        $this->assertTrue($maria->grupos->contains($grupo));
        $this->assertNotEmpty($maria->token);

        $this->actingAs($admin)->get(route('contactos.index', ['clientes', 'buscar' => 'María García']))
            ->assertOk()->assertSee('Constructora García');

        $this->actingAs($admin)->put(route('contactos.update', ['clientes', $maria]), [
            'nombres' => 'María José', 'apellidos' => 'García', 'correo' => 'maria@correo.com', 'sede_id' => $this->guatemala->id,
        ])->assertRedirect(route('contactos.index', 'clientes'));
        $this->assertSame('María José', $maria->fresh()->nombres);
        $this->assertCount(0, $maria->fresh()->grupos);
        $this->assertFalse($maria->fresh()->acepta_correos);

        // Desactivado: se conserva, sale de la lista normal y aparece en el filtro de inactivos
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('contactos.destroy'));
        $this->actingAs($admin)->patch(route('contactos.estado', ['clientes', $maria]))->assertSessionHas('success');
        $this->assertModelExists($maria);
        $this->assertFalse($maria->fresh()->activo);
        $this->actingAs($admin)->get(route('dashboard')); // consume el mensaje de confirmación
        $this->actingAs($admin)->get(route('contactos.index', 'clientes'))->assertDontSee('Constructora García');
        $this->actingAs($admin)->get(route('contactos.index', ['clientes', 'estado' => 'inactivos']))->assertSee('Constructora García');
    }

    public function test_validaciones_y_duplicados_de_contacto(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $this->padre(['correo' => 'repetido@correo.com']);

        $this->actingAs($admin)->post(route('contactos.store', 'clientes'), [
            'nombres' => '', 'apellidos' => 'X', 'correo' => 'repetido@correo.com', 'dpi' => '12', 'telefono' => '1', 'sede_id' => 999,
        ])->assertSessionHasErrors(['nombres', 'correo', 'dpi', 'telefono', 'sede_id']);

        // Un correo distinto sí se acepta
        $this->actingAs($admin)->post(route('contactos.store', 'clientes'), [
            'nombres' => 'Luis', 'apellidos' => 'Morales', 'correo' => 'otro@correo.com', 'sede_id' => $this->chiquimula->id, 'oficio' => 'Instalador de tabla yeso',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contactos', ['correo' => 'otro@correo.com', 'oficio' => 'Instalador de tabla yeso']);
    }

    public function test_usuario_limitado_a_su_sede(): void
    {
        $directora = $this->usuario('Director de sede', $this->xela);
        $deGuatemala = $this->padre(['nombres' => 'Pedro']);
        $deXela = $this->padre(['nombres' => 'Lucía', 'sede_id' => $this->xela->id]);

        $this->actingAs($directora)->get(route('contactos.index', 'clientes'))
            ->assertOk()->assertSee('Lucía')->assertDontSee('Pedro');
        $this->actingAs($directora)->get(route('contactos.edit', ['clientes', $deGuatemala]))->assertForbidden();
        $this->actingAs($directora)->get(route('contactos.edit', ['clientes', $deXela]))->assertOk();

        // No puede registrar contactos en otra sede
        $this->actingAs($directora)->post(route('contactos.store', 'clientes'), [
            'nombres' => 'X', 'apellidos' => 'Y', 'sede_id' => $this->guatemala->id,
        ])->assertSessionHasErrors('sede_id');
    }

    public function test_secretaria_no_puede_desactivar_ni_administrar_sedes(): void
    {
        $secretaria = $this->usuario('Secretaría');
        $p = $this->padre();

        $this->actingAs($secretaria)->get(route('contactos.index', 'clientes'))->assertOk();
        $this->actingAs($secretaria)->patch(route('contactos.estado', ['clientes', $p]))->assertForbidden();
        $this->actingAs($secretaria)->get(route('sedes.create'))->assertForbidden();
        $this->actingAs($secretaria)->post(route('contactos.masivo', 'clientes'), ['accion' => 'desactivar', 'ids' => [$p->id]])->assertForbidden();
        $this->assertTrue($p->fresh()->activo);
    }

    public function test_acciones_masivas_con_grupos(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $grupo = Grupo::create(['nombre' => 'Comité', 'tipo' => Contacto::CLIENTE, 'sede_id' => $this->guatemala->id]);
        $a = $this->padre();
        $b = $this->padre();
        $deChiquimula = $this->padre(['sede_id' => $this->chiquimula->id]);

        $this->actingAs($admin)->post(route('contactos.masivo', 'clientes'), [
            'accion' => 'agregar_grupo', 'grupo_id' => $grupo->id, 'ids' => [$a->id, $b->id, $deChiquimula->id],
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'Se agregaron 2') && str_contains($m, '1 no se agregaron'));
        $this->assertSame(2, $grupo->contactos()->count());

        $this->actingAs($admin)->get(route('grupos.show', $grupo))->assertOk()->assertSee($a->nombre_completo);

        $this->actingAs($admin)->post(route('contactos.masivo', 'clientes'), [
            'accion' => 'quitar_grupo', 'grupo_id' => $grupo->id, 'ids' => [$a->id],
        ]);
        $this->assertSame(1, $grupo->contactos()->count());

        $this->actingAs($admin)->post(route('contactos.masivo', 'clientes'), ['accion' => 'desactivar', 'ids' => [$a->id, $b->id]]);
        $this->assertSame(3, Contacto::count());
        $this->assertSame(1, Contacto::activos()->count());

        $this->actingAs($admin)->post(route('contactos.masivo', 'clientes'), ['accion' => 'activar', 'ids' => [$a->id]]);
        $this->assertSame(2, Contacto::activos()->count());
    }

    public function test_crud_de_grupos(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);

        $this->actingAs($admin)->post(route('grupos.store'), [
            'nombre' => 'Claustro básico', 'tipo' => Contacto::CLIENTE, 'sede_id' => $this->chiquimula->id,
        ])->assertRedirect();
        $grupo = Grupo::where('nombre', 'Claustro básico')->firstOrFail();

        // Nombre repetido en la misma sede
        $this->actingAs($admin)->post(route('grupos.store'), [
            'nombre' => 'Claustro básico', 'tipo' => Contacto::CLIENTE, 'sede_id' => $this->chiquimula->id,
        ])->assertSessionHasErrors('nombre');

        $this->actingAs($admin)->get(route('grupos.index'))->assertOk()->assertSee('Claustro básico');
        // Los grupos no se eliminan: el inactivo ya no se ofrece al registrar clientes
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('grupos.destroy'));
        $this->actingAs($admin)->patch(route('grupos.estado', $grupo))->assertSessionHas('success');
        $this->assertFalse($grupo->fresh()->activo);
        $this->actingAs($admin)->get(route('dashboard')); // consume el mensaje de confirmación
        $this->actingAs($admin)->get(route('grupos.index'))->assertDontSee('Claustro básico');
        $this->actingAs($admin)->get(route('grupos.index', ['estado' => 'inactivos']))->assertSee('Claustro básico');
        $this->actingAs($admin)->get(route('contactos.create', 'clientes'))->assertDontSee('Claustro básico');
    }

    public function test_plantilla_de_excel_se_descarga_con_sus_columnas(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);

        $resp = $this->actingAs($admin)->get(route('contactos.plantilla', 'clientes'));
        $resp->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'pla').'.xlsx';
        file_put_contents($ruta, $resp->streamedContent());

        $hoja = IOFactory::load($ruta)->getSheet(0);
        $this->assertSame(['Nombres', 'Apellidos', 'DPI', 'Correo', 'Teléfono', 'Sede', 'Empresa', 'Oficio', 'Grupos'],
            $hoja->rangeToArray('A1:I1')[0]);
    }

    public function test_importar_excel_crea_actualiza_y_reporta_errores(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $existente = $this->padre(['correo' => 'existente@correo.com', 'nombres' => 'Viejo', 'telefono' => '1111-2222']);

        $archivo = $this->excel([
            ['Nombres', 'Apellidos', 'DPI', 'Correo', 'Teléfono', 'Sede', 'Empresa', 'Oficio', 'Grupos'],
            ['Ana', 'López', '2584 73691 0207', 'ana@correo.com', '+502 5874 3210', 'ciudad de guatemala', 'Constructora López', 'Contratista', 'Instaladores, Comité'],
            ['Nuevo', 'Nombre', '', 'EXISTENTE@correo.com', '', 'Ciudad de Guatemala', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],                                         // vacía: se ignora
            ['Sin', 'Sede', '', 'sinsede@correo.com', '', '', '', '', ''],                // error: falta sede
            ['Mal', 'Correo', '', 'no-es-correo', '', 'Chiquimula', '', '', ''],           // error: correo inválido
            ['Otra', 'Sede', '', 'otra@correo.com', '', 'Antigua Guatemala', '', '', ''], // error: sede no existe
            ['Repetida', 'Fila', '', 'ana@correo.com', '', 'Quetzaltenango', '', '', ''], // error: repetida en archivo
            ['Beto', 'Chen', '', '', '4478-5632', 'CHIQUIMULA', 'Ferretería Chen', 'Propietario de ferretería', ''],
        ]);

        $this->actingAs($admin)->post(route('contactos.importar.store', 'clientes'), ['archivo' => $archivo, 'existentes' => 'actualizar'])
            ->assertRedirect(route('contactos.importar', 'clientes'));

        $r = session('resultado');
        $this->assertSame(7, $r['total']);
        $this->assertSame(2, $r['creados']);
        $this->assertSame(1, $r['actualizados']);
        $this->assertCount(4, $r['errores']);
        $this->assertSame([5, 6, 7, 8], array_column($r['errores'], 'fila'));
        $this->assertStringContainsString('fila 2', $r['errores'][3]['mensaje']);

        $ana = Contacto::where('correo', 'ana@correo.com')->firstOrFail();
        $this->assertSame('2584736910207', $ana->dpi);
        $this->assertSame('5874-3210', $ana->telefono);
        $this->assertSame($this->guatemala->id, $ana->sede_id);
        $this->assertEqualsCanonicalizing(['Instaladores', 'Comité'], $ana->grupos->pluck('nombre')->all());

        // Se actualiza el nombre pero el teléfono vacío no borra el que ya tenía
        $existente->refresh();
        $this->assertSame('Nuevo', $existente->nombres);
        $this->assertSame('1111-2222', $existente->telefono);

        $this->assertSame($this->chiquimula->id, Contacto::where('nombres', 'Beto')->value('sede_id'));

        $this->actingAs($admin)->get(route('contactos.importar', 'clientes'))->assertOk();
    }

    public function test_importar_sin_actualizar_existentes_y_con_grupo_destino(): void
    {
        $grupo = Grupo::create(['nombre' => 'Todos', 'tipo' => Contacto::CLIENTE, 'sede_id' => null]);
        $existente = $this->padre(['correo' => 'yaesta@correo.com', 'nombres' => 'Original']);

        $r = (new ImportadorContactos(Contacto::CLIENTE, actualizarExistentes: false, grupoDestino: $grupo))->procesar(collect([
            ['nombres' => 'Cambio', 'apellidos' => 'X', 'correo' => 'yaesta@correo.com', 'sede' => 'Ciudad de Guatemala'],
            ['nombres' => 'Nueva', 'apellidos' => 'Persona', 'correo' => 'nueva@correo.com', 'sede' => 'Quetzaltenango'],
        ]));

        $this->assertSame(1, $r['omitidos']);
        $this->assertSame(1, $r['creados']);
        $this->assertSame('Original', $existente->fresh()->nombres);
        $this->assertSame(2, $grupo->contactos()->count());
    }

    public function test_importar_limitado_a_la_sede_del_usuario(): void
    {
        $r = (new ImportadorContactos(Contacto::CLIENTE, sedeRestringida: $this->chiquimula->id))->procesar(collect([
            ['nombres' => 'Sin', 'apellidos' => 'Sede', 'correo' => 'a@correo.com', 'oficio' => 'Física'],
            ['nombres' => 'Otra', 'apellidos' => 'Sede', 'correo' => 'b@correo.com', 'sede' => 'Ciudad de Guatemala'],
        ]));

        $this->assertSame(1, $r['creados']);
        $this->assertCount(1, $r['errores']);
        $this->assertSame($this->chiquimula->id, Contacto::where('correo', 'a@correo.com')->value('sede_id'));
    }

    public function test_archivo_sin_columnas_obligatorias(): void
    {
        $r = (new ImportadorContactos(Contacto::CLIENTE))->procesar(collect([['telefono' => '5874-3210']]));

        $this->assertSame(0, $r['creados']);
        $this->assertStringContainsString('columnas obligatorias', $r['errores'][0]['mensaje']);
    }

    public function test_exportar_contactos(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $this->padre(['nombres' => 'Exportado', 'empresa' => 'Hijo']);

        $resp = $this->actingAs($admin)->get(route('contactos.exportar', 'clientes'));
        $resp->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
        file_put_contents($ruta, $resp->streamedContent());

        $filas = IOFactory::load($ruta)->getSheet(0)->toArray();
        $this->assertSame('Exportado', $filas[1][0]);
        $this->assertSame('Ciudad de Guatemala', $filas[1][5]);
    }
}
