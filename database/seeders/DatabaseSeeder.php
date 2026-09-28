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
        // Catálogo de Guatemala, roles/permisos y plantillas iniciales de correo y certificado.
        $this->call([
            GeografiaSeeder::class,
            RolesPermisosSeeder::class,
            PlantillasSeeder::class,
            CertificadosSeeder::class,
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
    }
}
