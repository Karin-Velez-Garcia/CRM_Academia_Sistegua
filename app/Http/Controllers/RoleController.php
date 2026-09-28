<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller implements HasMiddleware
{
    public const ETIQUETAS_MODULOS = [
        'usuarios' => 'Usuarios',
        'roles' => 'Roles y permisos',
        'geografia' => 'Departamentos y municipios',
        'sedes' => 'Sedes',
        'contactos' => 'Padres de familia y catedráticos',
        'eventos' => 'Reuniones y capacitaciones',
        'campanias' => 'Invitaciones y plantillas',
        'reportes' => 'Reportes',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.ver', only: ['index']),
            new Middleware('permission:roles.crear', only: ['create', 'store']),
            new Middleware('permission:roles.editar', only: ['edit', 'update']),
            new Middleware('permission:roles.eliminar', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'totalPermisos' => Permission::count(),
        ]);
    }

    public function create(): View
    {
        return view('roles.create', ['rol' => new Role(), 'asignados' => []] + $this->matriz());
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $rol = Role::create(['name' => $datos['name'], 'guard_name' => 'web']);
        $rol->syncPermissions($datos['permisos'] ?? []);

        return redirect()->route('roles.index')->with('success', "Se creó el rol {$rol->name}.");
    }

    public function edit(Role $rol): View
    {
        return view('roles.edit', [
            'rol' => $rol,
            'asignados' => $rol->permissions->pluck('name')->all(),
        ] + $this->matriz());
    }

    public function update(Request $request, Role $rol): RedirectResponse
    {
        if ($rol->name === User::ROL_ADMINISTRADOR) {
            return back()->with('error', 'El rol Administrador siempre tiene todos los permisos y no se puede modificar.');
        }

        $datos = $this->validar($request, $rol);

        $rol->update(['name' => $datos['name']]);
        $rol->syncPermissions($datos['permisos'] ?? []);

        return redirect()->route('roles.index')->with('success', "Se actualizó el rol {$rol->name}.");
    }

    public function destroy(Role $rol): RedirectResponse
    {
        if ($rol->name === User::ROL_ADMINISTRADOR) {
            return back()->with('error', 'El rol Administrador no se puede eliminar.');
        }

        if ($rol->users()->exists()) {
            return back()->with('error', "No se puede eliminar el rol {$rol->name} porque tiene usuarios asignados.");
        }

        $nombre = $rol->name;
        $rol->delete();

        return redirect()->route('roles.index')->with('success', "Se eliminó el rol {$nombre}.");
    }

    private function validar(Request $request, ?Role $rol = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($rol)],
            'permisos' => ['array'],
            'permisos.*' => [Rule::exists('permissions', 'name')],
        ], [], ['name' => 'nombre del rol']);
    }

    /**
     * Módulos y acciones para mostrar los permisos como una tabla de casillas.
     */
    private function matriz(): array
    {
        $acciones = collect(RolesPermisosSeeder::MODULOS)->flatten()->unique()->values();

        return [
            'modulos' => RolesPermisosSeeder::MODULOS,
            'etiquetas' => self::ETIQUETAS_MODULOS,
            'acciones' => $acciones,
        ];
    }
}
