<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Services\Qr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Asistencia por QR:
 *  1) QR del evento (público): se proyecta o imprime en el salón; cada asistente lo escanea
 *     con su celular y se registra con su correo o DPI.
 *  2) QR personal (personal del colegio): viene en la invitación de cada persona; en la entrada
 *     alguien del colegio lo escanea y la asistencia queda marcada.
 */
class RegistroQrController extends Controller
{
    // ---------------------------------------------------------------- QR del evento (público)

    public function show(string $token): View
    {
        return view('publico.registro', ['evento' => $this->eventoPorToken($token)]);
    }

    public function registrar(Request $request, string $token): RedirectResponse
    {
        $evento = $this->eventoPorToken($token);
        $datos = $request->validate(['identificador' => ['required', 'string', 'max:150']], [
            'identificador.required' => 'Escriba su correo electrónico o su número de DPI.',
        ]);

        if (! $evento->registro_abierto) {
            return back()->with('error', $evento->cancelado_at
                ? 'Esta actividad fue cancelada.'
                : 'El registro está abierto desde una hora antes del inicio hasta media hora después del final.');
        }

        $contacto = $this->buscarContacto($evento, trim($datos['identificador']));
        if (! $contacto) {
            return back()->withInput()->with('error',
                'No encontramos sus datos en la sede '.$evento->sede->nombre.'. Por favor acérquese a la mesa de registro.');
        }

        $inv = Invitacion::where('evento_id', $evento->id)->where('contacto_id', $contacto->id)->first();
        $yaEstaba = $inv?->asistio === true;
        if (! $yaEstaba) {
            Invitacion::registrarAsistencia($evento, $contacto, true, null, 'qr_evento');
        }

        return redirect()->route('registro.show', $token)->with('registrado', [
            'nombre' => $contacto->nombre_completo,
            'ya_estaba' => $yaEstaba,
        ]);
    }

    /** Página para proyectar o imprimir el QR del evento (personal del colegio). */
    public function cartel(Request $request, string $tipo, Evento $evento): View
    {
        $this->autorizar($request, $tipo, $evento);

        return view('asistencia.qr', [
            'segmento' => $tipo,
            'evento' => $evento->load('sede'),
            'qr' => Qr::dataUri($evento->urlRegistro(), 12),
        ]);
    }

    public function descargarPng(Request $request, string $tipo, Evento $evento): Response
    {
        $this->autorizar($request, $tipo, $evento);

        return response(Qr::png($evento->urlRegistro(), 14), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="qr-asistencia-'.Str::slug($evento->titulo).'.png"',
        ]);
    }

    /** Genera un enlace nuevo (el QR anterior deja de funcionar), por si se compartió por error. */
    public function renovar(Request $request, string $tipo, Evento $evento): RedirectResponse
    {
        $this->autorizar($request, $tipo, $evento);
        $evento->forceFill(['token_registro' => (string) Str::uuid()])->save();

        return back()->with('success', 'Se generó un QR nuevo. El anterior ya no funciona.');
    }

    // ---------------------------------------------------------------- QR personal (lo escanea el colegio)

    public function escanear(Request $request, string $token): View
    {
        abort_unless($request->user()->can('eventos.asistencia'), 403);
        $invitacion = Invitacion::with(['evento.sede', 'contacto'])->where('token', $token)->firstOrFail();
        $evento = $invitacion->evento;
        $sede = $request->user()->sedeRestringida();
        abort_if($sede && $evento->sede_id !== $sede, 403);

        $resultado = match (true) {
            $evento->cancelado_at !== null => 'cancelado',
            ! $evento->registro_abierto && $evento->inicio->isFuture() => 'antes',
            ! $evento->registro_abierto => 'terminado',
            $invitacion->asistio === true => 'ya_estaba',
            default => 'registrado',
        };

        if ($resultado === 'registrado') {
            Invitacion::registrarAsistencia($evento, $invitacion->contacto, true, $request->user()->id, 'qr_personal');
        }

        return view('asistencia.escaneo', [
            'invitacion' => $invitacion->fresh(['evento.sede', 'contacto']),
            'evento' => $evento,
            'resultado' => $resultado,
            'presentes' => $evento->invitaciones()->asistentes()->count(),
        ]);
    }

    // ----------------------------------------------------------------

    private function eventoPorToken(string $token): Evento
    {
        return Evento::with('sede.municipio.departamento')->where('token_registro', $token)->firstOrFail();
    }

    /** Busca por correo o DPI entre las personas de la sede del evento. */
    private function buscarContacto(Evento $evento, string $identificador): ?Contacto
    {
        $digitos = preg_replace('/\D/', '', $identificador);
        $query = Contacto::where('sede_id', $evento->sede_id);

        if (str_contains($identificador, '@')) {
            return $query->where('correo', Str::lower($identificador))->first();
        }
        if (strlen($digitos) === 13) {
            return $query->where('dpi', $digitos)->first();
        }

        return null;
    }

    private function autorizar(Request $request, string $tipo, Evento $evento): void
    {
        abort_unless($request->user()->can('eventos.asistencia'), 403);
        abort_if(! isset(Evento::TIPOS[$tipo]) || Evento::TIPOS[$tipo]['tipo'] !== $evento->tipo, 404);
        $sede = $request->user()->sedeRestringida();
        abort_if($sede && $evento->sede_id !== $sede, 403);
    }
}
