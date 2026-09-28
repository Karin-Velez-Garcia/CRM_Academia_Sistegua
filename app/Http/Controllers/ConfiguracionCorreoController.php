<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionCorreo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ConfiguracionCorreoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:correo.ver', only: ['edit']),
            new Middleware('permission:correo.editar', only: ['update', 'probar']),
        ];
    }

    public function edit(): View
    {
        return view('correo.edit', ['config' => ConfiguracionCorreo::actual()->loadMissing('actualizadoPor')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $smtp = $request->input('modo') === ConfiguracionCorreo::SMTP;

        $datos = $request->validate([
            'modo' => ['required', Rule::in(array_keys(ConfiguracionCorreo::MODOS))],
            'host' => [Rule::requiredIf($smtp), 'nullable', 'string', 'max:150'],
            'puerto' => [Rule::requiredIf($smtp), 'nullable', 'integer', 'between:1,65535'],
            'cifrado' => ['required', Rule::in(array_keys(ConfiguracionCorreo::CIFRADOS))],
            'usuario' => ['nullable', 'string', 'max:150'],
            'password' => ['nullable', 'string', 'max:255'],
            'remitente_correo' => ['required', 'email', 'max:150'],
            'remitente_nombre' => ['required', 'string', 'max:100'],
        ], [], [
            'host' => 'servidor SMTP',
            'usuario' => 'usuario',
            'password' => 'contraseña',
            'remitente_correo' => 'correo del remitente',
            'remitente_nombre' => 'nombre del remitente',
        ]);

        $config = ConfiguracionCorreo::actual();

        if (blank($datos['password'])) {
            // En blanco: se conserva la contraseña guardada (o la del .env la primera vez)
            $datos['password'] = $config->exists ? $config->password : config('mail.mailers.smtp.password');
        } elseif (str_ends_with((string) $datos['host'], 'gmail.com')) {
            // Google muestra la contraseña de aplicación en bloques separados por espacios
            $datos['password'] = str_replace(' ', '', $datos['password']);
        }

        $config->fill($datos + ['actualizado_por' => $request->user()->id])->save();
        $config->aplicar();

        return redirect()->route('correo.edit')->with('success', 'Se guardó la configuración de correo. '
            .($config->modo === ConfiguracionCorreo::SMTP ? 'Envíe un correo de prueba para confirmar que funciona.' : 'Los correos no se enviarán mientras esté en modo de prueba.'));
    }

    public function probar(Request $request): RedirectResponse
    {
        $datos = $request->validate(['destino' => ['required', 'email', 'max:150']], [], ['destino' => 'correo de destino']);

        $config = ConfiguracionCorreo::actual();
        $config->aplicar();

        try {
            Mail::raw(
                "Este es un correo de prueba de {$config->remitente_nombre}.\n\n"
                ."Si lo está leyendo, la configuración de envío funciona correctamente.\n\n"
                .'Enviado el '.now()->format('d/m/Y H:i').' por '.$request->user()->name.'.',
                fn ($m) => $m->to($datos['destino'])->subject('Correo de prueba — '.$config->remitente_nombre)
            );
        } catch (Throwable $e) {
            Log::warning('Falló el correo de prueba', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'No se pudo enviar el correo de prueba: '.$this->explicar($e->getMessage()));
        }

        return back()->with('success', $config->modo === ConfiguracionCorreo::SMTP
            ? "Se envió el correo de prueba a {$datos['destino']}. Revise la bandeja de entrada (y la de spam)."
            : 'Modo de prueba: el correo quedó en storage/logs/correos.log y no se envió.');
    }

    /** Traduce los errores SMTP más comunes a algo que el usuario pueda corregir. */
    private function explicar(string $error): string
    {
        return match (true) {
            str_contains($error, '535'), str_contains($error, 'Username and Password not accepted') =>
                'el servidor rechazó el usuario o la contraseña. En Gmail debe usar una contraseña de aplicación, no la clave normal de la cuenta.',
            str_contains($error, 'Connection could not be established'), str_contains($error, 'timed out') =>
                'no se pudo conectar con el servidor. Revise el servidor, el puerto y el cifrado.',
            default => $error,
        };
    }
}
