<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermisosSeeder extends Seeder
{
    /**
     * Módulos del sistema y las acciones que se pueden permitir en cada uno.
     */
    public const MODULOS = [
        'usuarios' => ['ver', 'crear', 'editar', 'eliminar'],
        'roles' => ['ver', 'crear', 'editar', 'eliminar'],
        'geografia' => ['ver'],
        'sedes' => ['ver', 'crear', 'editar', 'eliminar'],
        'contactos' => ['ver', 'crear', 'editar', 'eliminar', 'importar'],
        'eventos' => ['ver', 'crear', 'editar', 'eliminar', 'asistencia'],
        'campanias' => ['ver', 'crear', 'editar', 'eliminar', 'enviar'],
        'reportes' => ['ver', 'exportar'],
    ];

    /** Permisos iniciales de cada rol (el Administrador tiene todos). */
    public const ROLES = [
        // Dirige una sede: contactos, reuniones, capacitaciones, invitaciones y asistencia
        'Director de sede' => [
            'geografia.ver',
            'sedes.ver',
            'contactos.ver', 'contactos.crear', 'contactos.editar', 'contactos.eliminar', 'contactos.importar',
            'eventos.ver', 'eventos.crear', 'eventos.editar', 'eventos.eliminar', 'eventos.asistencia',
            'campanias.ver', 'campanias.crear', 'campanias.editar', 'campanias.eliminar', 'campanias.enviar',
            'reportes.ver', 'reportes.exportar',
        ],
        // Mantiene los contactos al día, envía las convocatorias y toma asistencia
        'Secretaría' => [
            'geografia.ver',
            'sedes.ver',
            'contactos.ver', 'contactos.crear', 'contactos.editar', 'contactos.importar',
            'eventos.ver', 'eventos.asistencia',
            'campanias.ver', 'campanias.crear', 'campanias.enviar',
            'reportes.ver',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $nuevos = [];
        foreach (self::MODULOS as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                $permiso = Permission::firstOrCreate(['name' => "{$modulo}.{$accion}", 'guard_name' => 'web']);
                if ($permiso->wasRecentlyCreated) {
                    $nuevos[] = $permiso->name;
                }
            }
        }

        // El Administrador tiene acceso total mediante Gate::before (AppServiceProvider)
        Role::firstOrCreate(['name' => User::ROL_ADMINISTRADOR, 'guard_name' => 'web'])
            ->syncPermissions(Permission::all());

        foreach (self::ROLES as $nombre => $permisos) {
            $rol = Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
            if ($rol->wasRecentlyCreated) {
                $rol->syncPermissions($permisos);
            } else {
                // Rol existente: solo se agregan los permisos nuevos, sin tocar los ajustes hechos en la aplicación
                $rol->givePermissionTo(array_values(array_intersect($permisos, $nuevos)));
            }
        }
    }
}
