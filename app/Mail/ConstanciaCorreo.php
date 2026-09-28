<?php

namespace App\Mail;

use App\Http\Controllers\ConstanciaController;
use App\Models\Invitacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConstanciaCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitacion $invitacion)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Constancia de participación: '.$this->invitacion->evento->titulo);
    }

    public function content(): Content
    {
        return new Content(view: 'correos.constancia', with: [
            'evento' => $this->invitacion->evento,
            'contacto' => $this->invitacion->contacto,
            'codigo' => $this->invitacion->codigoConstancia(),
        ]);
    }

    public function attachments(): array
    {
        $inv = $this->invitacion->loadMissing(['evento', 'contacto']);

        return [
            Attachment::fromData(
                fn () => ConstanciaController::pdf($inv->evento, collect([$inv]))->output(),
                'constancia-'.str($inv->contacto->nombre_completo)->slug().'.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
