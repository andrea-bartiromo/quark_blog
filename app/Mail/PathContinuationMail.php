<?php

namespace App\Mail;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Il Percorso continua" — notifica editoriale minimale (Parte 12).
 * Riusa il branding Kairus già stabilito (colore #0d9488, impaginazione a
 * card) invece di introdurre un secondo stile: stesso linguaggio visivo di
 * NewsletterController/SendNewsletterJob.
 *
 * Costruita SOLO dal job (SendPathContinuationNotification), sempre con un
 * $article già ri-verificato pubblicato al momento dell'invio — mai con
 * dati potenzialmente scaduti (Parte 13: nessun leak di contenuto non
 * pubblico).
 */
class PathContinuationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContentCluster $cluster,
        public readonly Article $article,
        public readonly string $unsubscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Il Percorso continua: '.$this->article->title,
        );
    }

    public function content(): Content
    {
        $articleUrl = route('articolo', $this->article->slug);
        $excerpt = trim((string) $this->article->excerpt);

        $excerptHtml = $excerpt !== ''
            ? "<p style='color:#374151;line-height:1.7;margin:0 0 1.25rem;'>{$excerpt}</p>"
            : '';

        $html = "
            <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:2rem;background:#ffffff;'>
                <div style='text-align:center;margin-bottom:2rem;'>
                    <div style='width:64px;height:64px;background:#0d9488;border-radius:50%;
                                display:inline-flex;align-items:center;justify-content:center;
                                font-size:1.8rem;margin-bottom:1rem;'>🧭</div>
                    <h1 style='font-size:1.6rem;color:#111827;margin:0 0 .25rem;font-weight:900;'>Kairus.</h1>
                    <p style='color:#6b7280;font-size:.82rem;margin:0;'>La scienza spiegata come si deve</p>
                </div>
                <p style='color:#0d9488;font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;margin:0 0 .35rem;'>
                    Percorso Kairus · {$this->cluster->name}
                </p>
                <h2 style='font-size:1.3rem;color:#111827;margin:0 0 1rem;'>È disponibile una nuova tappa.</h2>
                <div style='border:1px solid #e5e7eb;border-radius:10px;padding:1.25rem;margin-bottom:1.5rem;'>
                    <h3 style='margin:0 0 .5rem;font-size:1.1rem;color:#111827;'>{$this->article->title}</h3>
                    {$excerptHtml}
                    <a href='{$articleUrl}'
                       style='display:inline-block;background:#0d9488;color:#fff;
                              padding:.7rem 1.5rem;border-radius:8px;text-decoration:none;
                              font-weight:700;font-size:.92rem;'>
                        Continua il percorso →
                    </a>
                </div>
                <hr style='border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0;'>
                <p style='color:#9ca3af;font-size:.72rem;text-align:center;margin:0;line-height:1.6;'>
                    Hai ricevuto questa email perché segui il Percorso “{$this->cluster->name}” su Kairus.<br>
                    <a href='{$this->unsubscribeUrl}' style='color:#9ca3af;'>Non ricevere più aggiornamenti per questo Percorso</a>.
                </p>
            </div>
        ";

        return new Content(htmlString: $html);
    }
}
