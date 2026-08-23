<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentCluster;

/**
 * Growth S2 — "Continua da qui": sceglie UNA sola prosecuzione editoriale
 * forte dopo la lettura di un articolo, o nessuna. Deliberatamente NON un
 * motore di recommendation generico: niente scoring, niente AI/embeddings,
 * niente personalizzazione, un solo candidato per chiamata.
 *
 * Gerarchia dei candidati, in ordine:
 * 1. il prossimo articolo pubblico dello stesso Percorso attivo (riusa
 *    ArticlePathNavigation — nessuna logica di ordering/cluster duplicata
 *    qui, la scelta del Percorso "primario" quando un articolo appartiene a
 *    più Percorsi è già lì, deterministica: is_primary, poi sort_order,
 *    nome, id);
 * 2. altrimenti, l'articolo più recente della stessa categoria PRINCIPALE
 *    (non le secondarie: un segnale più debole non merita di guidare
 *    l'unica prosecuzione mostrata al lettore).
 *
 * Nessun fallback editoriale generico (es. "featured più recente" senza
 * relazione con l'articolo corrente): un suggerimento scollegato dal tema
 * appena letto è peggio di nessun suggerimento. Restituire null è quindi
 * un esito normale e atteso, non un caso limite da coprire con un
 * ripiego debole.
 */
class ArticleContinuationService
{
    public function __construct(
        private readonly ArticlePathNavigation $pathNavigation
    ) {}

    /**
     * @param  array{cluster:ContentCluster,current_index:int,total:int,previous:?Article,next:?Article,path_url:string}|null  $pathNavigation
     *                                                                                                                                          Passa il risultato già calcolato da ArticlePathNavigation::forArticle()
     *                                                                                                                                          quando il chiamante lo possiede già (es. ArticleController::show()), per
     *                                                                                                                                          evitare di ripetere la stessa query. Se omesso, viene calcolato qui.
     */
    public function forArticle(Article $article, ?array $pathNavigation = null): ?Article
    {
        $navigation = $pathNavigation ?? $this->pathNavigation->forArticle($article);

        if ($navigation && $navigation['next'] instanceof Article) {
            return $navigation['next'];
        }

        return $this->categoryContinuation($article, $navigation);
    }

    private function categoryContinuation(Article $article, ?array $navigation): ?Article
    {
        // Se il Percorso ha già un "precedente" mostrato in pagina, non lo
        // si ripropone qui come se fosse una nuova scoperta: il lettore lo
        // ha appena visto nel blocco di navigazione del Percorso.
        $excludeIds = [$article->id];

        if ($navigation && $navigation['previous'] instanceof Article) {
            $excludeIds[] = $navigation['previous']->id;
        }

        return Article::published()
            ->where('category', $article->category)
            ->whereNotIn('id', $excludeIds)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }
}
