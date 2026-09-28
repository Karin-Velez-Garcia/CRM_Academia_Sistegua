<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            GeografiaSeeder::class,
            SedesSeeder::class,
            RolesPermisosSeeder::class,
            PlantillasSeeder::class,
        ]);

        // Usuario administrador inicial: cambie la contraseña después del primer ingreso.
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@sistegua.com')],
            [
                'name' => 'Administrador',
                'apellidos' => 'General',
                'password' => env('ADMIN_PASSWORD', 'Admin12345'),
                'activo' => true,
            ]
        );
        $admin->assignRole(User::ROL_ADMINISTRADOR);

        // Datos de demostración: clientes, grupos, capacitaciones e invitaciones con asistencia.
        $this->call([
            ClientesSeeder::class,
            GruposDemoSeeder::class,
            EventosDemoSeeder::class,
        ]);
    }
}
