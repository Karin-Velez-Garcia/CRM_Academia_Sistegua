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

    private Sede $sanarate;
    private Sede $salama;
    private Sede $coban;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, SedesSeeder::class, RolesPermisosSeeder::class]);
        $this->sanarate = Sede::where('nombre', 'Sanarate')->firstOrFail();
        $this->salama = Sede::where('nombre', 'Salamá')->firstOrFail();
        $this->coban = Sede::where('nombre', 'Cobán')->firstOrFail();
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
            'tipo' => Contacto::PADRE, 'sede_id' => $this->sanarate->id,
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

    public function test_sedes_del_colegio_estan_cargadas(): void
    {
        $this->assertSame(['Cobán', 'Salamá', 'Sanarate'], Sede::orderBy('nombre')->pluck('nombre')->all());
        $this->assertSame('El Progreso', $this->sanarate->municipio->departamento->nombre);
        $this->assertSame('Baja Verapaz', $this->salama->municipio->departamento->nombre);
        $this->assertSame('Alta Verapaz', $this->coban->municipio->departamento->nombre);
    }

    public function test_crud_de_sedes_y_proteccion_al_eliminar(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);

        $this->actingAs($admin)->get(route('sedes.index'))->assertOk()->assertSee('Sede Sanarate');
        $this->actingAs($admin)->post(route('sedes.store'), [
            'nombre' => 'Guastatoya', 'municipio_id' => $this->sanarate->municipio_id - 6, 'activa' => '1', 'telefono' => '79451234',
            'direccion' => 'Barrio El Centro, Guastatoya',
        ])->assertRedirect(route('sedes.index'));
        $this->assertDatabaseHas('sedes', ['nombre' => 'Guastatoya']);

        $this->padre();
        $this->actingAs($admin)->delete(route('sedes.destroy', $this->sanarate))->assertSessionHas('error');
        $this->assertModelExists($this->sanarate);
    }

    public function test_crear_editar_y_eliminar_padre_de_familia(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $grupo = Grupo::create(['nombre' => 'Padres 3.º Básico', 'tipo' => Contacto::PADRE, 'sede_id' => $this->sanarate->id]);

        $this->actingAs($admin)->get(route('contactos.create', 'padres'))->assertOk()->assertSee('Nombre del estudiante');

        $this->actingAs($admin)->post(route('contactos.store', 'padres'), [
            'nombres' => 'María', 'apellidos' => 'García', 'correo' => 'MARIA@Correo.com', 'telefono' => '58743210',
            'dpi' => '2584736910207', 'sede_id' => $this->sanarate->id, 'estudiante' => 'Ana García',
            'grado_seccion' => '3.º Básico A', 'acepta_correos' => '1', 'grupos' => [$grupo->id],
        ])->assertRedirect(route('contactos.index', 'padres'));

        $maria = Contacto::where('correo', 'maria@correo.com')->firstOrFail();
        $this->assertSame(Contacto::PADRE, $maria->tipo);
        $this->assertSame('5874-3210', $maria->telefono);
        $this->assertTrue($maria->grupos->contains($grupo));
        $this->assertNotEmpty($maria->token);

        $this->actingAs($admin)->get(route('contactos.index', ['padres', 'buscar' => 'María García']))->assertOk()->assertSee('Ana García');

        $this->actingAs($admin)->put(route('contactos.update', ['padres', $maria]), [
            'nombres' => 'María José', 'apellidos' => 'García', 'correo' => 'maria@correo.com', 'sede_id' => $this->sanarate->id,
        ])->assertRedirect(route('contactos.index', 'padres'));
        $this->assertSame('María José', $maria->fresh()->nombres);
        $this->assertCount(0, $maria->fresh()->grupos);
        $this->assertFalse($maria->fresh()->acepta_correos);

        // Un padre no se puede editar desde la ruta de catedráticos
        $this->actingAs($admin)->get(route('contactos.edit', ['catedraticos', $maria]))->assertNotFound();

        $this->actingAs($admin)->delete(route('contactos.destroy', ['padres', $maria]))->assertRedirect();
        $this->assertModelMissing($maria);
    }

    public function test_validaciones_y_duplicados_de_contacto(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $this->padre(['correo' => 'repetido@correo.com']);

        $this->actingAs($admin)->post(route('contactos.store', 'padres'), [
            'nombres' => '', 'apellidos' => 'X', 'correo' => 'repetido@correo.com', 'dpi' => '12', 'telefono' => '1', 'sede_id' => 999,
        ])->assertSessionHasErrors(['nombres', 'correo', 'dpi', 'telefono', 'sede_id']);

        // El mismo correo sí puede existir como catedrático (tipo distinto)
        $this->actingAs($admin)->post(route('contactos.store', 'catedraticos'), [
            'nombres' => 'Luis', 'apellidos' => 'Morales', 'correo' => 'repetido@correo.com', 'sede_id' => $this->coban->id, 'area' => 'Matemática',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contactos', ['tipo' => Contacto::CATEDRATICO, 'correo' => 'repetido@correo.com', 'area' => 'Matemática']);
    }

    public function test_usuario_limitado_a_su_sede(): void
    {
        $directora = $this->usuario('Director de sede', $this->salama);
        $deSanarate = $this->padre(['nombres' => 'Pedro']);
        $deSalama = $this->padre(['nombres' => 'Lucía', 'sede_id' => $this->salama->id]);

        $this->actingAs($directora)->get(route('contactos.index', 'padres'))
            ->assertOk()->assertSee('Lucía')->assertDontSee('Pedro');
        $this->actingAs($directora)->get(route('contactos.edit', ['padres', $deSanarate]))->assertForbidden();
        $this->actingAs($directora)->get(route('contactos.edit', ['padres', $deSalama]))->assertOk();

        // No puede registrar contactos en otra sede
        $this->actingAs($directora)->post(route('contactos.store', 'padres'), [
            'nombres' => 'X', 'apellidos' => 'Y', 'sede_id' => $this->sanarate->id,
        ])->assertSessionHasErrors('sede_id');
    }

    public function test_secretaria_no_puede_eliminar_ni_administrar_sedes(): void
    {
        $secretaria = $this->usuario('Secretaría');
        $p = $this->padre();

        $this->actingAs($secretaria)->get(route('contactos.index', 'padres'))->assertOk();
        $this->actingAs($secretaria)->delete(route('contactos.destroy', ['padres', $p]))->assertForbidden();
        $this->actingAs($secretaria)->get(route('sedes.create'))->assertForbidden();
        $this->actingAs($secretaria)->post(route('contactos.masivo', 'padres'), ['accion' => 'eliminar', 'ids' => [$p->id]])->assertForbidden();
        $this->assertModelExists($p);
    }

    public function test_acciones_masivas_con_grupos(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $grupo = Grupo::create(['nombre' => 'Comité', 'tipo' => Contacto::PADRE, 'sede_id' => $this->sanarate->id]);
        $a = $this->padre();
        $b = $this->padre();
        $deCoban = $this->padre(['sede_id' => $this->coban->id]);

        $this->actingAs($admin)->post(route('contactos.masivo', 'padres'), [
            'accion' => 'agregar_grupo', 'grupo_id' => $grupo->id, 'ids' => [$a->id, $b->id, $deCoban->id],
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'Se agregaron 2') && str_contains($m, '1 no se agregaron'));
        $this->assertSame(2, $grupo->contactos()->count());

        $this->actingAs($admin)->get(route('grupos.show', $grupo))->assertOk()->assertSee($a->nombre_completo);

        $this->actingAs($admin)->post(route('contactos.masivo', 'padres'), [
            'accion' => 'quitar_grupo', 'grupo_id' => $grupo->id, 'ids' => [$a->id],
        ]);
        $this->assertSame(1, $grupo->contactos()->count());

        $this->actingAs($admin)->post(route('contactos.masivo', 'padres'), ['accion' => 'eliminar', 'ids' => [$a->id, $b->id]]);
        $this->assertSame(1, Contacto::count());
    }

    public function test_crud_de_grupos(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);

        $this->actingAs($admin)->post(route('grupos.store'), [
            'nombre' => 'Claustro básico', 'tipo' => Contacto::CATEDRATICO, 'sede_id' => $this->coban->id,
        ])->assertRedirect();
        $grupo = Grupo::where('nombre', 'Claustro básico')->firstOrFail();

        // Nombre repetido en la misma sede
        $this->actingAs($admin)->post(route('grupos.store'), [
            'nombre' => 'Claustro básico', 'tipo' => Contacto::CATEDRATICO, 'sede_id' => $this->coban->id,
        ])->assertSessionHasErrors('nombre');

        $this->actingAs($admin)->get(route('grupos.index'))->assertOk()->assertSee('Claustro básico');
        $this->actingAs($admin)->delete(route('grupos.destroy', $grupo))->assertRedirect(route('grupos.index'));
        $this->assertModelMissing($grupo);
    }

    public function test_plantilla_de_excel_se_descarga_con_sus_columnas(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);

        $resp = $this->actingAs($admin)->get(route('contactos.plantilla', 'padres'));
        $resp->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'pla').'.xlsx';
        file_put_contents($ruta, $resp->streamedContent());

        $hoja = IOFactory::load($ruta)->getSheet(0);
        $this->assertSame(['Nombres', 'Apellidos', 'DPI', 'Correo', 'Teléfono', 'Sede', 'Estudiante', 'Grado y sección', 'Grupos'],
            $hoja->rangeToArray('A1:I1')[0]);
    }

    public function test_importar_excel_crea_actualiza_y_reporta_errores(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $existente = $this->padre(['correo' => 'existente@correo.com', 'nombres' => 'Viejo', 'telefono' => '1111-2222']);

        $archivo = $this->excel([
            ['Nombres', 'Apellidos', 'DPI', 'Correo', 'Teléfono', 'Sede', 'Estudiante', 'Grado y sección', 'Grupos'],
            ['Ana', 'López', '2584 73691 0207', 'ana@correo.com', '+502 5874 3210', 'sanarate', 'Luis López', '1.º Básico', 'Padres 1.º Básico, Comité'],
            ['Nuevo', 'Nombre', '', 'EXISTENTE@correo.com', '', 'Sanarate', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],                                         // vacía: se ignora
            ['Sin', 'Sede', '', 'sinsede@correo.com', '', '', '', '', ''],                // error: falta sede
            ['Mal', 'Correo', '', 'no-es-correo', '', 'Cobán', '', '', ''],               // error: correo inválido
            ['Otra', 'Sede', '', 'otra@correo.com', '', 'Quetzaltenango', '', '', ''],    // error: sede no existe
            ['Repetida', 'Fila', '', 'ana@correo.com', '', 'Salama', '', '', ''],         // error: repetida en archivo
            ['Beto', 'Chen', '', '', '4478-5632', 'COBAN', 'Mía Chen', '2.º Primaria', ''],
        ]);

        $this->actingAs($admin)->post(route('contactos.importar.store', 'padres'), ['archivo' => $archivo, 'existentes' => 'actualizar'])
            ->assertRedirect(route('contactos.importar', 'padres'));

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
        $this->assertSame($this->sanarate->id, $ana->sede_id);
        $this->assertEqualsCanonicalizing(['Padres 1.º Básico', 'Comité'], $ana->grupos->pluck('nombre')->all());

        // Se actualiza el nombre pero el teléfono vacío no borra el que ya tenía
        $existente->refresh();
        $this->assertSame('Nuevo', $existente->nombres);
        $this->assertSame('1111-2222', $existente->telefono);

        $this->assertSame($this->coban->id, Contacto::where('nombres', 'Beto')->value('sede_id'));

        $this->actingAs($admin)->get(route('contactos.importar', 'padres'))->assertOk();
    }

    public function test_importar_sin_actualizar_existentes_y_con_grupo_destino(): void
    {
        $grupo = Grupo::create(['nombre' => 'Todos', 'tipo' => Contacto::PADRE, 'sede_id' => null]);
        $existente = $this->padre(['correo' => 'yaesta@correo.com', 'nombres' => 'Original']);

        $r = (new ImportadorContactos(Contacto::PADRE, actualizarExistentes: false, grupoDestino: $grupo))->procesar(collect([
            ['nombres' => 'Cambio', 'apellidos' => 'X', 'correo' => 'yaesta@correo.com', 'sede' => 'Sanarate'],
            ['nombres' => 'Nueva', 'apellidos' => 'Persona', 'correo' => 'nueva@correo.com', 'sede' => 'Salamá'],
        ]));

        $this->assertSame(1, $r['omitidos']);
        $this->assertSame(1, $r['creados']);
        $this->assertSame('Original', $existente->fresh()->nombres);
        $this->assertSame(2, $grupo->contactos()->count());
    }

    public function test_importar_limitado_a_la_sede_del_usuario(): void
    {
        $r = (new ImportadorContactos(Contacto::CATEDRATICO, sedeRestringida: $this->coban->id))->procesar(collect([
            ['nombres' => 'Sin', 'apellidos' => 'Sede', 'correo' => 'a@correo.com', 'area' => 'Física'],
            ['nombres' => 'Otra', 'apellidos' => 'Sede', 'correo' => 'b@correo.com', 'sede' => 'Sanarate'],
        ]));

        $this->assertSame(1, $r['creados']);
        $this->assertCount(1, $r['errores']);
        $this->assertSame($this->coban->id, Contacto::where('correo', 'a@correo.com')->value('sede_id'));
    }

    public function test_archivo_sin_columnas_obligatorias(): void
    {
        $r = (new ImportadorContactos(Contacto::PADRE))->procesar(collect([['telefono' => '5874-3210']]));

        $this->assertSame(0, $r['creados']);
        $this->assertStringContainsString('columnas obligatorias', $r['errores'][0]['mensaje']);
    }

    public function test_exportar_contactos(): void
    {
        $admin = $this->usuario(User::ROL_ADMINISTRADOR);
        $this->padre(['nombres' => 'Exportado', 'estudiante' => 'Hijo']);

        $resp = $this->actingAs($admin)->get(route('contactos.exportar', 'padres'));
        $resp->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
        file_put_contents($ruta, $resp->streamedContent());

        $filas = IOFactory::load($ruta)->getSheet(0)->toArray();
        $this->assertSame('Exportado', $filas[1][0]);
        $this->assertSame('Sanarate', $filas[1][5]);
    }
}
