<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Carbon;

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
