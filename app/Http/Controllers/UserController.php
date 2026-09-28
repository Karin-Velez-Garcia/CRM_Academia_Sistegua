<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:usuarios.ver', only: ['index']),
            new Middleware('permission:usuarios.crear', only: ['create', 'store']),
            new Middleware('permission:usuarios.editar', only: ['edit', 'update', 'toggleActivo']),
            new Middleware('permission:usuarios.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $usuarios = User::with(['roles', 'sede'])
            ->buscar($request->input('buscar'))
            ->when($request->filled('rol'), fn ($q) => $q->role($request->input('rol')))
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->input('estado') === 'activo'))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function create(): View
    {
        return view('usuarios.create', [
            'usuario' => new User(['activo' => true]),
            'roles' => Role::orderBy('name')->pluck('name'),
            'sedes' => Sede::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $usuario = User::create($datos);
        $usuario->syncRoles([$datos['rol']]);

        return redirect()->route('usuarios.index')->with('success', "Se creó el usuario {$usuario->nombre_completo}.");
    }

    public function edit(User $usuario): View
    {
        return view('usuarios.edit', [
            'usuario' => $usuario,
            'roles' => Role::orderBy('name')->pluck('name'),
            'sedes' => Sede::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $this->validar($request, $usuario);

        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        if ($error = $this->protegerAdministradores($request, $usuario, $datos['rol'], (bool) $datos['activo'])) {
            return back()->withInput()->with('error', $error);
        }

        $usuario->update($datos);
        $usuario->syncRoles([$datos['rol']]);

        return redirect()->route('usuarios.index')->with('success', "Se actualizó el usuario {$usuario->nombre_completo}.");
    }

    public function toggleActivo(Request $request, User $usuario): RedirectResponse
    {
        $nuevoEstado = ! $usuario->activo;

        if ($error = $this->protegerAdministradores($request, $usuario, $usuario->getRoleNames()->first(), $nuevoEstado)) {
            return back()->with('error', $error);
        }

        $usuario->update(['activo' => $nuevoEstado]);

        return back()->with('success', $nuevoEstado
            ? "Se activó a {$usuario->nombre_completo}."
            : "Se desactivó a {$usuario->nombre_completo}.");
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puede eliminar su propio usuario.');
        }

        if ($usuario->hasRole(User::ROL_ADMINISTRADOR) && $this->administradoresActivos() <= 1) {
            return back()->with('error', 'No se puede eliminar al último administrador activo.');
        }

        $nombre = $usuario->nombre_completo;
        $usuario->delete();

        return redirect()->route('usuarios.index')->with('success', "Se eliminó el usuario {$nombre}.");
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($usuario)],
            'dpi' => ['nullable', 'digits:13', Rule::unique('users')->ignore($usuario)],
            'telefono' => ['nullable', 'regex:/^[0-9]{4}-?[0-9]{4}$/'],
            'sede_id' => ['nullable', Rule::exists('sedes', 'id')],
            'rol' => ['required', Rule::exists('roles', 'name')],
            'activo' => ['boolean'],
            'password' => [$usuario ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'telefono.regex' => 'El teléfono debe tener 8 dígitos (por ejemplo 7945-1234).',
        ], [
            'name' => 'nombres',
            'dpi' => 'DPI',
            'rol' => 'rol',
            'sede_id' => 'sede',
        ]);

        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }

    /**
     * Evita quedarse sin administradores o bloquearse a uno mismo.
     */
    private function protegerAdministradores(Request $request, User $usuario, ?string $nuevoRol, bool $activo): ?string
    {
        $esAdmin = $usuario->hasRole(User::ROL_ADMINISTRADOR);
        $dejaDeSerAdmin = $esAdmin && ($nuevoRol !== User::ROL_ADMINISTRADOR || ! $activo);

        if ($usuario->is($request->user()) && ! $activo) {
            return 'No puede desactivar su propio usuario.';
        }

        if ($dejaDeSerAdmin && $usuario->activo && $this->administradoresActivos() <= 1) {
            return 'Debe existir al menos un administrador activo.';
        }

        return null;
    }

    private function administradoresActivos(): int
    {
        return User::role(User::ROL_ADMINISTRADOR)->where('activo', true)->count();
    }
}
