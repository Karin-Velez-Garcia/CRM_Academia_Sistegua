<?php

namespace App\Http\Controllers;

use App\Http\Controllers\EventoController;
use App\Models\Invitacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Páginas públicas para el invitado (sin iniciar sesión), identificadas por el token de su invitación.
 *
 * Los botones del correo solo abren la página: la respuesta se guarda al pulsar el botón (POST),
 * para que los filtros de correo que abren enlaces automáticamente no respondan por el invitado.
 */
class RespuestaController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitacion = $this->buscar($token);
        if (! $invitacion->vista_at) {
            $invitacion->forceFill(['vista_at' => now()])->save();
        }

        return view('publico.invitacion', [
            'invitacion' => $invitacion,
            'evento' => $invitacion->evento,
            'sugerida' => $request->input('r'),
        ]);
    }

    public function responder(Request $request, string $token): RedirectResponse
    {
        $invitacion = $this->buscar($token);
        $evento = $invitacion->evento;

        $datos = $request->validate([
            'respuesta' => ['required', Rule::in([Invitacion::CONFIRMADA, Invitacion::RECHAZADA])],
            'comentario' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $evento->acepta_respuestas) {
            return back()->with('error', $evento->cancelado_at
                ? 'Esta actividad fue cancelada.'
                : 'Esta actividad ya se realizó; ya no se reciben respuestas.');
        }

        if ($datos['respuesta'] === Invitacion::CONFIRMADA
            && $invitacion->respuesta !== Invitacion::CONFIRMADA && $evento->cupo_lleno) {
            return back()->with('error', 'Lo sentimos, ya no hay cupos disponibles para esta capacitación.');
        }

        $invitacion->update([
            'respuesta' => $datos['respuesta'],
            'comentario' => $datos['comentario'] ?? null,
            'respuesta_por' => 'invitado',
            'respondida_at' => now(),
        ]);

        return redirect()->route('invitacion.show', $token)->with('respondido', $datos['respuesta']);
    }

    public function calendario(string $token): Response
    {
        $evento = $this->buscar($token)->evento;

        return response(EventoController::ics($evento), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.str($evento->titulo)->slug().'.ics"',
        ]);
    }

    public function bajaForm(string $token): View
    {
        $invitacion = $this->buscar($token);

        return view('publico.baja', ['invitacion' => $invitacion, 'contacto' => $invitacion->contacto]);
    }

    public function baja(string $token): RedirectResponse
    {
        $invitacion = $this->buscar($token);
        $invitacion->contacto->update(['acepta_correos' => false]);

        return redirect()->route('invitacion.baja', $token)->with('dado_de_baja', true);
    }

    private function buscar(string $token): Invitacion
    {
        return Invitacion::with(['evento.sede.municipio.departamento', 'contacto'])
            ->where('token', $token)->firstOrFail();
    }
}
