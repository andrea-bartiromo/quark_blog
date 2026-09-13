<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Presentation-only: decide SE e QUANDO mostrare "Aggiornato il" su un
 * articolo pubblico, leggendo solo article_revisions così com'è già
 * scritto da ArticleRevisionService (fuori scope, mai modificato qui).
 * Non introduce campi, non modifica come le revisioni vengono create o
 * classificate.
 *
 * Perché non basta l'ultima revisione qualsiasi: ArticleRevisionService
 * scrive una riga PRE-CHANGE anche quando cambia solo `status` (es. una
 * transizione draft -> published, o un repubblicazione senza modifiche di
 * contenuto) — vedi docs/article-revision-history.md e
 * ArticleRevisionService::SNAPSHOT_FIELDS. Usare ciecamente
 * MAX(created_at) mostrerebbe "Aggiornato il" anche quando nulla di
 * editorialmente rilevante è cambiato dopo la pubblicazione.
 *
 * Regola applicata (conservativa, mai una data inventata):
 * 1. Servono una o più revisioni con created_at > published_at (attività
 *    editoriale avvenuta DOPO la pubblicazione attuale).
 * 2. La più vecchia di queste deve differire dallo stato ATTUALE
 *    dell'articolo su almeno uno tra title/excerpt/body/category — prova
 *    che il contenuto è davvero cambiato, non solo lo status.
 * 3. Se entrambe le condizioni valgono, la data mostrata è quella della
 *    revisione più RECENTE tra quelle qualificate (approssima il momento
 *    dell'ultimo salvataggio post-pubblicazione).
 */
class ArticleRevisionTransparencyService
{
    private const CONTENT_FIELDS = ['title', 'excerpt', 'body', 'category'];

    public function lastEditorialUpdate(Article $article): ?Carbon
    {
        if (! $article->published_at) {
            return null;
        }

        // reorder(): Article::revisions() applica già ->latest('created_at')
        // (per l'uso admin, dove serve la più recente in cima) — qui invece
        // serve un ordine cronologico esplicito, altrimenti orderBy()
        // aggiungerebbe una seconda clausola sulla stessa colonna senza
        // sostituire quella ereditata dalla relazione.
        $qualifying = $article->revisions()
            ->where('created_at', '>', $article->published_at)
            ->reorder('created_at', 'asc')
            ->get();

        if ($qualifying->isEmpty()) {
            return null;
        }

        $earliest = $qualifying->first();

        if (! $this->contentDiffers($earliest, $article)) {
            return null;
        }

        return $qualifying->last()->created_at;
    }

    /**
     * Stessa regola di lastEditorialUpdate() applicata in blocco a una
     * collezione di articoli, a costo costante rispetto al numero di
     * revisioni storiche — MAI un `get()` di ogni revisione mai scritta
     * (inclusi gli snapshot pre-pubblicazione e il loro `body` longText):
     * i confini (prima/ultima revisione qualificante) sono calcolati lato
     * DB con MIN/MAX su una singola colonna, e il `body` viene caricato
     * SOLO per la revisione più vecchia di ciascun articolo — l'unica
     * confrontata da contentDiffers() (Codex, PR #589: l'implementazione
     * precedente caricava l'intera cronologia di ogni articolo pubblico,
     * crescendo con la dimensione totale del corpus storico, non con il
     * numero di articoli).
     *
     * Introdotto dal Cantiere 3 "Kairus Organic Discovery"
     * (OrganicDiscoveryReadinessService), primo chiamante che ha bisogno
     * del segnale di freschezza su tutto il corpus pubblico in una volta
     * sola — prima d'ora lastEditorialUpdate() veniva invocato solo per un
     * singolo articolo alla volta (pagina pubblica dell'articolo).
     *
     * @param  Collection<int, Article>  $articles
     * @return Collection<int, Carbon> chiavi = article_id, presenti SOLO
     *                                 per gli articoli con un aggiornamento editoriale qualificante
     *                                 (stesso significato di lastEditorialUpdate() !== null).
     */
    public function lastEditorialUpdates(Collection $articles): Collection
    {
        $published = $articles->filter(fn (Article $article) => $article->published_at !== null);

        if ($published->isEmpty()) {
            return collect();
        }

        // Solo i confini temporali (mai il body): una riga per articolo,
        // con la revisione qualificante più vecchia e quella più recente,
        // filtrando già qui created_at > published_at tramite il join —
        // le revisioni pre-pubblicazione non vengono nemmeno lette.
        $bounds = ArticleRevision::query()
            ->join('articles', 'articles.id', '=', 'article_revisions.article_id')
            ->whereIn('article_revisions.article_id', $published->pluck('id'))
            ->whereColumn('article_revisions.created_at', '>', 'articles.published_at')
            ->groupBy('article_revisions.article_id')
            ->selectRaw('article_revisions.article_id as article_id, min(article_revisions.created_at) as earliest_at, max(article_revisions.created_at) as latest_at')
            ->get()
            ->keyBy('article_id');

        if ($bounds->isEmpty()) {
            return collect();
        }

        // Il body serve SOLO per la revisione più vecchia di ciascun
        // articolo (l'unica confrontata da contentDiffers()) — mai per le
        // altre revisioni qualificanti nel mezzo.
        $earliestRevisions = ArticleRevision::query()
            ->where(function ($query) use ($bounds) {
                foreach ($bounds as $bound) {
                    $query->orWhere(function ($query) use ($bound) {
                        $query->where('article_id', $bound->article_id)
                            ->where('created_at', $bound->earliest_at);
                    });
                }
            })
            ->get()
            ->keyBy('article_id');

        return $published->mapWithKeys(function (Article $article) use ($bounds, $earliestRevisions) {
            $bound = $bounds->get($article->id);

            if ($bound === null) {
                return [$article->id => null];
            }

            $earliest = $earliestRevisions->get($article->id);

            if ($earliest === null || ! $this->contentDiffers($earliest, $article)) {
                return [$article->id => null];
            }

            return [$article->id => Carbon::parse($bound->latest_at)];
        })->filter();
    }

    private function contentDiffers($revision, Article $article): bool
    {
        foreach (self::CONTENT_FIELDS as $field) {
            if ((string) $revision->getAttribute($field) !== (string) $article->getAttribute($field)) {
                return true;
            }
        }

        return false;
    }
}
