<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PerfilController extends Controller
{
    public function edit(Request $request): View
    {
        return view('perfil.edit', ['usuario' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($usuario)],
            'dpi' => ['nullable', 'digits:13', Rule::unique('users')->ignore($usuario)],
            'telefono' => ['nullable', 'regex:/^[0-9]{4}-?[0-9]{4}$/'],
        ], [
            'telefono.regex' => 'El teléfono debe tener 8 dígitos (por ejemplo 7945-1234).',
        ], ['name' => 'nombres', 'dpi' => 'DPI']);

        $usuario->update($datos);

        return back()->with('success', 'Sus datos se actualizaron.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'password_actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['password_actual' => 'contraseña actual', 'password' => 'nueva contraseña']);

        $request->user()->update(['password' => $request->input('password')]);

        return back()->with('success', 'Su contraseña se cambió correctamente.');
    }
}
