<?php

namespace App\Mail;

use App\Services\Communication\RenderedCampaignMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Involucro Mailable minimale attorno a un RenderedCampaignMessage già
 * pronto (MailerEmailProvider è l'unico chiamante) — non ri-renderizza
 * nulla: CampaignRenderer resta l'unica fonte di html/text, questa
 * classe si limita a impacchettarli in un vero messaggio email
 * inviabile dal Mailer di Laravel. htmlString evita un secondo file
 * Blade di solo passthrough; il testo alternativo è impostato via
 * withSymfonyMessage() per lo stesso motivo (nessuna view necessaria
 * per un valore già-stringa).
 */
class CommunicationTestSendMailable extends Mailable
{
    public function __construct(
        public readonly RenderedCampaignMessage $rendered,
    ) {
        $this->withSymfonyMessage(function ($message): void {
            $message->text($this->rendered->text);
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->rendered->fromEmail
                ? new Address($this->rendered->fromEmail, $this->rendered->fromName ?? '')
                : null,
            replyTo: $this->rendered->replyTo ? [new Address($this->rendered->replyTo)] : [],
            subject: $this->rendered->subject,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->rendered->html);
    }
}
