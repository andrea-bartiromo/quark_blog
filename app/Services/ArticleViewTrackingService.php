<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleView;
use Illuminate\Support\Facades\DB;

/**
 * Registra una view pubblica valida di un articolo: aggiorna il contatore
 * cumulativo (Article::views), il log per-pageview già esistente
 * (article_views) e il nuovo aggregato giornaliero (article_daily_views) —
 * tutti e tre secondo la STESSA regola di ammissione, così i tre numeri
 * restano sempre coerenti tra loro.
 *
 * Traffico interno escluso: qualunque utente autenticato con accesso
 * all'area redazionale (admin, editor, author — User::canAccessRedazione())
 * non viene conteggiato. Deliberatamente NON si usa l'IP come segnale: un
 * redattore in mobilità o su rete condivisa avrebbe lo stesso IP di lettori
 * reali, e una lista di IP personali non sarebbe né affidabile né
 * manutenibile. Nessun filtro bot/crawler: il progetto non ha oggi un
 * meccanismo di rilevamento, e introdurne uno tramite euristiche sullo
 * User-Agent rischierebbe falsi positivi/negativi non verificabili in
 * questo blocco — lasciato esplicitamente fuori scope (caso ambiguo
 * documentato, non un'omissione silenziosa).
 */
class ArticleViewTrackingService
{
    /**
     * True per qualunque visitatore che non sia autenticato con accesso
     * all'area redazionale (guest, o utente autenticato "solo lettore").
     */
    public function shouldCountRequest(): bool
    {
        $user = auth()->user();

        return ! $user || ! $user->canAccessRedazione();
    }

    /**
     * Giorno editoriale corrente (Europe/Rome, non UTC) nel formato Y-m-d
     * usato dalla colonna `date` di article_daily_views.
     */
    public function editorialToday(): string
    {
        return now()->timezone(Article::EDITORIAL_TIMEZONE)->toDateString();
    }

    /**
     * Le tre scritture (log per-pageview, contatore lifetime, bucket
     * giornaliero) avvengono in un'unica transazione: se una fallisce a
     * metà, nessuna delle tre resta committata da sola. Senza questo, un
     * fallimento parziale lascerebbe i tre numeri incoerenti tra loro — e
     * poiché il controller marca la sessione come "vista" solo quando
     * questo metodo restituisce true, un nuovo tentativo dopo un
     * fallimento a metà rieseguirebbe le scritture già andate a buon fine,
     * contando la stessa view due volte.
     *
     * Restituisce true solo se la view è stata davvero registrata: il
     * chiamante deve marcare la sessione come "vista" esclusivamente in
     * quel caso — altrimenti un flag di sessione impostato per una
     * richiesta di traffico interno (mai contata) potrebbe in teoria
     * mascherare una successiva view pubblica genuina nella stessa
     * sessione (es. dopo un logout che non invalidasse la sessione).
     */
    public function recordView(Article $article): bool
    {
        if (! $this->shouldCountRequest()) {
            return false;
        }

        DB::transaction(function () use ($article) {
            ArticleView::create([
                'article_id' => $article->id,
                'ip_hash' => hash('sha256', request()->ip()),
                'user_agent' => substr((string) request()->userAgent(), 0, 1000),
                'referer' => substr((string) request()->headers->get('referer'), 0, 1000),
                'viewed_at' => now(),
            ]);

            $article->increment('views');

            $this->incrementDailyBucket($article->id, $this->editorialToday());
        });

        return true;
    }

    /**
     * Incremento atomico a livello di riga — "INSERT ... ON CONFLICT/ON
     * DUPLICATE KEY UPDATE views = views + 1", non una lettura seguita da
     * una scrittura: sicuro sotto richieste concorrenti sullo stesso
     * articolo nello stesso giorno editoriale, su tutti i driver
     * supportati dal progetto (stesso approccio già usato in
     * StatsController per le differenze di sintassi tra driver).
     */
    private function incrementDailyBucket(int $articleId, string $date): void
    {
        $now = now()->toDateTimeString();

        match (DB::connection()->getDriverName()) {
            'sqlite', 'pgsql' => DB::statement(
                'INSERT INTO article_daily_views (article_id, date, views, created_at, updated_at)
                 VALUES (?, ?, 1, ?, ?)
                 ON CONFLICT (article_id, date) DO UPDATE SET views = article_daily_views.views + 1, updated_at = ?',
                [$articleId, $date, $now, $now, $now]
            ),
            default => DB::statement(
                'INSERT INTO article_daily_views (article_id, date, views, created_at, updated_at)
                 VALUES (?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE views = views + 1, updated_at = ?',
                [$articleId, $date, $now, $now, $now]
            ),
        };
    }
}
