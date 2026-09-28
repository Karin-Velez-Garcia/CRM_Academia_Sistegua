<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['email' => 'correo electrónico', 'password' => 'contraseña']);

        $llave = Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($llave, 5)) {
            $segundos = RateLimiter::availableIn($llave);
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Intente de nuevo en {$segundos} segundos.",
            ]);
        }

        if (! Auth::attempt($credenciales + ['activo' => true], $request->boolean('recordar'))) {
            RateLimiter::hit($llave, 60);
            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos, o el usuario está desactivado.',
            ]);
        }

        RateLimiter::clear($llave);
        $request->session()->regenerate();
        $request->user()->forceFill(['ultimo_acceso_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
