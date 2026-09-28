<?php

namespace App\Http\Controllers;

use App\Mail\ConstanciaCorreo;
use App\Models\Envio;
use App\Models\Evento;
use App\Models\Invitacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Constancias de participación para quienes asistieron a una capacitación.
 */
class ConstanciaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:eventos.ver', only: ['descargar', 'todas']),
            new Middleware('permission:campanias.enviar', only: ['enviar']),
        ];
    }

    public function descargar(Request $request, Evento $evento, Invitacion $invitacion): Response
    {
        $this->autorizar($request, $evento);
        abort_unless($invitacion->evento_id === $evento->id && $invitacion->asistio, 404);

        return self::pdf($evento, collect([$invitacion->load('contacto')]))
            ->download('constancia-'.str($invitacion->contacto->nombre_completo)->slug().'.pdf');
    }

    /** Un solo PDF con una página por asistente (para imprimir todas). */
    public function todas(Request $request, Evento $evento): Response
    {
        $this->autorizar($request, $evento);
        $asistentes = $this->asistentes($evento);
        abort_if($asistentes->isEmpty(), 404, 'Nadie tiene asistencia registrada.');

        return self::pdf($evento, $asistentes)->download('constancias-'.str($evento->titulo)->slug().'.pdf');
    }

    /** Envía a cada asistente su constancia por correo. */
    public function enviar(Request $request, Evento $evento): RedirectResponse
    {
        $this->autorizar($request, $evento);
        $asistentes = $this->asistentes($evento)->filter(fn (Invitacion $i) => $i->contacto->correo);

        foreach ($asistentes as $inv) {
            Mail::to($inv->contacto->correo, $inv->contacto->nombre_completo)->queue(new ConstanciaCorreo($inv));
        }

        if ($asistentes->isNotEmpty()) {
            Envio::create([
                'evento_id' => $evento->id, 'motivo' => 'constancia', 'asunto' => 'Constancia de participación: '.$evento->titulo,
                'total' => $asistentes->count(), 'user_id' => $request->user()->id,
            ]);
        }

        $prueba = config('mail.default') === 'log' ? ' (Modo de prueba: se guardaron en storage/logs/correos.log.)' : '';

        return back()->with($asistentes->isEmpty() ? 'info' : 'success', $asistentes->isEmpty()
            ? 'Ningún asistente tiene correo registrado.'
            : "Se enviaron {$asistentes->count()} constancias por correo.{$prueba}");
    }

    /** Página pública para comprobar que una constancia es auténtica. */
    public function verificar(?string $codigo = null): View
    {
        $codigo = $codigo ? strtoupper(trim($codigo)) : (request('codigo') ? strtoupper(trim(request('codigo'))) : null);
        $invitacion = $codigo
            ? Invitacion::with(['evento.sede', 'contacto'])->where('codigo_constancia', $codigo)->where('asistio', true)->first()
            : null;

        return view('publico.verificar', ['codigo' => $codigo, 'invitacion' => $invitacion]);
    }

    public static function pdf(Evento $evento, Collection $invitaciones): \Barryvdh\DomPDF\PDF
    {
        $evento->loadMissing('sede.municipio.departamento');
        foreach ($invitaciones as $inv) {
            $inv->codigoConstancia();
        }

        return Pdf::loadView('pdf.constancia', ['evento' => $evento, 'invitaciones' => $invitaciones])
            ->setPaper('letter', 'landscape');
    }

    private function asistentes(Evento $evento): Collection
    {
        return $evento->invitaciones()->asistentes()->with('contacto')->get()
            ->sortBy(fn ($i) => mb_strtolower($i->contacto->apellidos.' '.$i->contacto->nombres))->values();
    }

    private function autorizar(Request $request, Evento $evento): void
    {
        abort_unless($evento->tipo === Evento::CAPACITACION, 404);
        $sede = $request->user()->sedeRestringida();
        abort_if($sede && $evento->sede_id !== $sede, 403);
    }
}
