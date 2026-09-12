<?php

namespace App\Mail;

use App\Models\Newsletter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterInitialConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Newsletter $subscriber,
        public readonly string $confirmUrl,
        public readonly string $unsubscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '🧪 Un ultimo passo — conferma la tua iscrizione a Kairus');
    }

    public function content(): Content
    {
        return new Content(htmlString: "
            <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:2rem;background:#fff;'>
              <h1 style='color:#111827;'>Kairus.</h1>
              <p style='color:#374151;line-height:1.7;'>Hai richiesto l'iscrizione alla newsletter Kairus. Per riceverla, conferma il tuo indirizzo email.</p>
              <p style='text-align:center;margin:1.5rem 0;'><a href='{$this->confirmUrl}' style='display:inline-block;background:#0d9488;color:#fff;padding:.85rem 2rem;border-radius:8px;text-decoration:none;font-weight:700;'>✅ Conferma l'iscrizione</a></p>
              <p style='color:#6b7280;font-size:.82rem;'>Se non hai richiesto questa iscrizione, ignora questa email oppure <a href='{$this->unsubscribeUrl}'>annullala qui</a>.</p>
            </div>
        ");
    }
}
