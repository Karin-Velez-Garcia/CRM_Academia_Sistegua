<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\SedesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableroTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicadores_graficas_y_avisos(): void
    {
        $this->seed([GeografiaSeeder::class, SedesSeeder::class, RolesPermisosSeeder::class]);
        $admin = User::factory()->create();
        $admin->assignRole(User::ROL_ADMINISTRADOR);
        $sanarate = Sede::where('nombre', 'Sanarate')->firstOrFail();
        $coban = Sede::where('nombre', 'Cobán')->firstOrFail();

        // Evento realizado hace 10 días con 4 invitados: 3 confirmaron, 2 asistieron
        $realizado = Evento::create(['tipo' => 'reunion', 'titulo' => 'Reunión pasada', 'sede_id' => $sanarate->id, 'modalidad' => 'presencial',
            'lugar' => 'Salón', 'para_todos' => true, 'inicio' => now()->subDays(10), 'fin' => now()->subDays(10)->addHours(2)]);
        foreach ([['confirmada', true], ['confirmada', true], ['confirmada', false], [null, null]] as $i => [$r, $a]) {
            $c = Contacto::create(['tipo' => 'padre', 'sede_id' => $sanarate->id, 'nombres' => "P$i", 'apellidos' => 'X', 'correo' => "p$i@correo.com"]);
            Invitacion::create(['evento_id' => $realizado->id, 'contacto_id' => $c->id, 'correo' => $c->correo,
                'estado_envio' => 'enviada', 'respuesta' => $r, 'asistio' => $a]);
        }
        Contacto::create(['tipo' => 'catedratico', 'sede_id' => $coban->id, 'nombres' => 'D', 'apellidos' => 'Y', 'correo' => null]);

        // Evento en 3 días sin invitaciones
        Evento::create(['tipo' => 'capacitacion', 'titulo' => 'Taller próximo', 'sede_id' => $coban->id, 'modalidad' => 'virtual',
            'enlace' => 'https://meet.google.com/x', 'para_todos' => true, 'inicio' => now()->addDays(3), 'fin' => now()->addDays(3)->addHours(2)]);

        $resp = $this->actingAs($admin)->get(route('dashboard'));
        $resp->assertOk()->assertSee('Invitaciones y asistencia por mes')->assertSee('Requiere atención');

        $kpi = $resp->viewData('kpi');
        $this->assertSame(4, $kpi['padres']);
        $this->assertSame(1, $kpi['catedraticos']);
        $this->assertSame(1, $kpi['proximos_30']);
        $this->assertSame(75, $kpi['tasa_confirmacion']);
        $this->assertSame(50, $kpi['tasa_asistencia']);

        $totales = $resp->viewData('totales');
        $this->assertSame(1, $totales['sin_respuesta']);
        $this->assertSame(2, collect($resp->viewData('porMes'))->sum('asistencias'));
        $this->assertSame([['sede' => 'Sanarate', 'eventos' => 1, 'enviadas' => 4, 'asistencias' => 2, 'tasa' => 50]], $resp->viewData('porSede'));

        $avisos = collect($resp->viewData('avisos'))->pluck(2)->join(' | ');
        $this->assertStringContainsString('Sin invitaciones: Taller próximo', $avisos);
        $this->assertStringContainsString('1 contacto sin correo', $avisos);
        $this->assertStringContainsString('Sedes sin dirección', $avisos);

        // Filtro por sede: Cobán no tiene eventos realizados
        $this->actingAs($admin)->get(route('dashboard', ['sede' => $coban->id]))->assertOk()
            ->assertSee('Todavía no hay eventos realizados en este período');

        // Un usuario limitado a Cobán ve solo su sede
        $dir = User::factory()->create(['sede_id' => $coban->id]);
        $dir->assignRole('Director de sede');
        $this->assertSame(0, $this->actingAs($dir)->get(route('dashboard'))->viewData('kpi')['padres']);
    }
}
