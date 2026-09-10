<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\Category;
use App\Models\Newsletter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Newsletter $subscriber;

    public function __construct(Newsletter $subscriber, public ?string $deliveryKey = null)
    {
        $this->subscriber = $subscriber;
    }

    /**
     * Perché Cache::add() e non il ledger CommunicationDelivery: quel
     * ledger vincola subscriber_id a una foreign key NOT NULL su
     * comm_subscribers, popolata da `newsletter` (questa tabella, la
     * fonte reale dei destinatari qui) solo tramite una copia manuale
     * one-shot (communication:migrate-subscribers) — non c'è alcuna
     * sincronizzazione continua tra le due tabelle. Instradare la
     * consegna sul ledger avrebbe richiesto o inventare al volo righe
     * comm_subscribers per iscritti `newsletter` non ancora migrati
     * (una scrittura collaterale mai richiesta, fuori scope) o escludere
     * dall'invio chi non è stato migrato (una modifica reale ai
     * destinatari, esplicitamente vietata). Cache::add() resta quindi la
     * primitiva corretta finché i destinatari della newsletter restano
     * il modello `Newsletter`: è un'operazione atomica reale sia sul
     * driver 'database' (insertOrIgnore contro la PRIMARY KEY di
     * `cache`) sia su 'file' (flock esclusivo), non un check-then-act.
     */
    public function handle(): void
    {
        $cacheKey = $this->deliveryKey ? 'newsletter:delivery:'.$this->deliveryKey : null;

        if ($cacheKey && ! Cache::add($cacheKey, true, now()->addDays(14))) {
            return;
        }

        $articles = $this->getArticles();

        if ($articles->isEmpty()) {
            if ($cacheKey) {
                Cache::forget($cacheKey);
            }

            return;
        }

        $intro = $this->generateIntro($articles);
        $html = $this->buildHtml($articles, $intro, $this->subscriber);

        try {
            Mail::send([], [], function ($message) use ($html) {
                $unsubUrl = route('newsletter.unsubscribe', [
                    'token' => $this->subscriber->unsubscribe_token,
                ]);

                $trackingPixel = "<img src='".route('newsletter.open', $this->subscriber->id)."' width='1' height='1' style='display:none;'>";

                $fullHtml = $html."
                    {$trackingPixel}

                    <div style='border-top:1px solid #e5e7eb;margin-top:2rem;padding-top:1rem;text-align:center;'>
                        <p style='color:#9ca3af;font-size:.72rem;margin:0;line-height:1.6;'>
                            Hai ricevuto questa email perché sei iscritto a Kairus.<br>
                            <a href='{$unsubUrl}' style='color:#9ca3af;'>Disiscriviti</a>
                        </p>
                    </div>
                </div>";

                $message->to($this->subscriber->email)
                    ->subject('🧪 Kairus — I migliori articoli della settimana')
                    ->html($fullHtml);
            });
        } catch (\Throwable $e) {
            if ($cacheKey) {
                Cache::forget($cacheKey);
            }

            // Il log operativo non deve diventare una copia del payload del
            // provider: email, token, header e messaggi arbitrari possono
            // contenere dati personali o segreti. Manteniamo solo riferimenti
            // interni e una classe d'errore a vocabolario controllabile.
            Log::error('Invio newsletter legacy fallito.', [
                'subscriber_id' => $this->subscriber->id,
                'error_class' => class_basename($e),
            ]);

            throw $e;
        }
    }

    private function getArticles()
    {
        $topRead = Article::published()
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('views')
            ->limit(3)
            ->get();

        if ($topRead->count() < 3) {
            $topRead = Article::published()
                ->orderByDesc('views')
                ->limit(3)
                ->get();
        }

        $topReadIds = $topRead->pluck('id')->toArray();

        $latest = Article::published()
            ->whereNotIn('id', $topReadIds)
            ->orderByDesc('published_at')
            ->limit(2)
            ->get();

        return $topRead->merge($latest);
    }

    private function generateIntro($articles): string
    {
        return 'Abbiamo selezionato per te gli articoli più interessanti della settimana. Scopri cosa sta succedendo nel mondo della scienza.';
    }

    private function buildHtml($articles, string $intro = '', ?Newsletter $subscriber = null): string
    {
        $base = config('app.url');

        // DB-first (stessa fonte di Category::options() usata altrove):
        // un articolo in una categoria creata dopo il deploy deve avere
        // un'etichetta leggibile nell'email reale inviata agli iscritti,
        // non degradare silenziosamente allo slug grezzo qui sotto.
        $cats = Category::options(false);

        $articlesHtml = '';

        foreach ($articles as $art) {
            $url = $subscriber
                ? route('newsletter.click', [$subscriber->id, $art->id])
                : $base.'/articolo/'.$art->slug;

            $cat = $cats[$art->category] ?? $art->category;

            $articlesHtml .= "
                <div style='margin-bottom:20px;border:1px solid #eee;padding:15px;border-radius:10px;'>
                    <p style='font-size:12px;color:#0d9488;font-weight:bold;margin:0 0 8px;'>{$cat}</p>
                    <h3>{$art->title}</h3>
                    <p>{$art->excerpt}</p>
                    <a href='{$url}' style='color:#0d9488;font-weight:bold;'>Leggi</a>
                </div>
            ";
        }

        return "
        <div style='font-family:Arial;max-width:600px;margin:auto;'>
            <h1>Kairus</h1>
            <p>{$intro}</p>
            {$articlesHtml}
        ";
    }
}
