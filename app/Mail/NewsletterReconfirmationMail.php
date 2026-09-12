<?php

namespace App\Mail;

use App\Models\Newsletter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sollecito di riconferma per un iscritto pendente (confirmed=false) —
 * mai un'email inviata automaticamente: costruita SOLO da
 * NewsletterReconfirmationService::send(), sempre su richiesta esplicita
 * di un editor da /admin/newsletter. Stesso branding di
 * NewsletterController::subscribe() (colore #0d9488, impaginazione a
 * card) e di PathContinuationMail — nessun nuovo linguaggio visivo.
 *
 * Non ripete il contenuto della prima email di conferma (che rimane
 * quella di NewsletterController::subscribe(), mai toccata da questa
 * classe): qui il testo dichiara esplicitamente che si tratta di un
 * secondo sollecito, con un link diverso (token dedicato, con scadenza).
 */
class NewsletterReconfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Newsletter $subscriber,
        public readonly string $confirmUrl,
        public readonly int $expiresInDays,
        public readonly int $attemptNumber,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🧪 Sei ancora interessato a Kairus? Conferma la tua iscrizione',
        );
    }

    public function content(): Content
    {
        $html = "
            <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:2rem;background:#ffffff;'>
                <div style='text-align:center;margin-bottom:2rem;'>
                    <div style='width:64px;height:64px;background:#0d9488;border-radius:50%;
                                display:inline-flex;align-items:center;justify-content:center;
                                font-size:1.8rem;margin-bottom:1rem;'>🧪</div>
                    <h1 style='font-size:1.6rem;color:#111827;margin:0 0 .25rem;font-weight:900;'>Kairus.</h1>
                    <p style='color:#6b7280;font-size:.82rem;margin:0;'>La scienza spiegata come si deve</p>
                </div>

                <h2 style='font-size:1.2rem;color:#111827;margin-bottom:.75rem;'>Promemoria {$this->attemptNumber} di 3, senza impegno.</h2>

                <p style='color:#374151;line-height:1.7;margin-bottom:1rem;'>
                    Tempo fa hai lasciato la tua email per iscriverti a Kairus, ma non risulta
                    ancora una conferma. Se sei ancora interessato a ricevere la nostra selezione
                    settimanale di articoli scientifici, ti basta un clic:
                </p>

                <div style='text-align:center;margin-bottom:1.5rem;'>
                    <a href='{$this->confirmUrl}'
                       style='display:inline-block;background:#0d9488;color:#fff;
                              padding:.85rem 2rem;border-radius:8px;text-decoration:none;
                              font-weight:700;font-size:1rem;'>
                        ✅ Sì, confermo l'iscrizione
                    </a>
                </div>

                <p style='color:#6b7280;font-size:.82rem;text-align:center;margin-bottom:1.5rem;'>
                    Questo link scade tra {$this->expiresInDays} giorni e può essere usato una sola volta.
                </p>

                <hr style='border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0;'>

                <p style='color:#9ca3af;font-size:.72rem;text-align:center;margin:0;line-height:1.6;'>
                    Se non vuoi più ricevere questa newsletter non devi fare nulla: senza conferma
                    l'iscrizione resta inattiva e verrà rimossa automaticamente dopo l'ultimo promemoria.
                </p>
            </div>
        ";

        return new Content(htmlString: $html);
    }
}
