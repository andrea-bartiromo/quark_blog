<?php

namespace App\Services\InternalLinking;

use App\Models\Article;
use App\Services\ArticleLinkInsertionService;

/**
 * Cantiere 82 (programma "100 cantieri Kairus"). Nessun codice esistente
 * verifica se accettare un suggerimento di collegamento interno
 * chiuderebbe un ciclo (A→B→...→A) tra i link interni GIÀ REALMENTE
 * PRESENTI nel contenuto pubblicato — confermato da un agente Explore
 * dedicato prima di scrivere questo file: zero occorrenze di
 * "cycle/ciclo/circular" in tutto `app/` e `tests/` per questo dominio, e
 * il resto del sistema (`ArticleLinkSuggestionService`,
 * `ArticleLinkSuggestionController`) è già un flusso completo
 * "proposta → conferma umana → applicazione solo al salvataggio reale"
 * (vedi quei file) — quella metà del titolo del cantiere ("conferma
 * umana") era già interamente costruita.
 *
 * Un ciclo di link interni non è di per sé un errore: due articoli
 * correlati che si linkano a vicenda, o un percorso di lettura
 * sequenziale che si richiude su un articolo hub, sono pattern
 * editoriali del tutto legittimi. Questo servizio non blocca né impedisce
 * nulla — è di sola lettura e non scrive mai — rende solo visibile
 * all'editor, nel pannello dei suggerimenti
 * (`ArticleLinkSuggestionController::serializeSuggestions()`), che
 * accettare un dato suggerimento chiuderebbe un ciclo: un'informazione
 * che oggi non esiste da nessuna parte, mai un giudizio "ciclo =
 * sbagliato". La decisione resta sempre dell'editor.
 *
 * Codex (PR #618): la prima versione derivava il grafo dalle sole righe
 * `ArticleLinkSuggestion::STATUS_ACCEPTED` — ma quella tabella diverge dal
 * contenuto reale in entrambe le direzioni. Un link inserito manualmente
 * in TinyMCE (senza mai passare da "Analizza"/"Inserisci") non genera mai
 * una riga 'accepted': il grafo lo ignorava, perdendo cicli reali.
 * All'opposto, `markAccepted()` promuove ad 'accepted' un suggerimento
 * solo quando il link viene davvero salvato, ma se la redazione rimuove
 * in seguito quel link dal body a mano (senza toccare mai più il
 * suggerimento), la riga resta 'accepted' per sempre: il grafo segnalava
 * cicli su collegamenti che nel contenuto pubblicato non esistono più.
 * Corretto usando la STESSA definizione di "collegamento ad articolo" già
 * riconosciuta ovunque nel dominio (badge Admin, audit
 * `content:internal-link-audit`): i veri tag `<a href="/articolo/...">`
 * nel body corrente di ogni articolo
 * (`ArticleLinkInsertionService::linkedArticleSlugsInBody()`), mai una
 * tabella di stato separata che può disallinearsi dal contenuto reale.
 */
class ArticleLinkCycleDetector
{
    public function __construct(
        private readonly ArticleLinkInsertionService $insertionService = new ArticleLinkInsertionService,
    ) {}

    /** @var array<int, list<int>>|null */
    private ?array $realEdges = null;

    /**
     * Verifica se accettare un arco source→target chiuderebbe un ciclo
     * attraverso i collegamenti REALMENTE presenti nel body di ogni
     * articolo del sito (mai una tabella di stato separata, sempre
     * soggetta a disallinearsi da ciò che è davvero pubblicato — vedi
     * nota Codex sopra la classe).
     *
     * @return array{creates_cycle: bool, path: list<int>} 'path', quando
     *                                                     creates_cycle è true, è il ciclo completo come sequenza di
     *                                                     id articolo: [source, target, ..., source].
     */
    public function detect(int $sourceArticleId, int $targetArticleId): array
    {
        if ($sourceArticleId === $targetArticleId) {
            return ['creates_cycle' => false, 'path' => []];
        }

        $edges = $this->realEdges();

        // BFS a partire da $targetArticleId sugli archi reali: se si
        // riesce a tornare a $sourceArticleId, accettare il nuovo arco
        // source→target chiuderebbe il ciclo.
        $visited = [$targetArticleId => true];
        $queue = [[$targetArticleId]];

        while ($queue !== []) {
            $path = array_shift($queue);
            $node = end($path);

            if ($node === $sourceArticleId) {
                return ['creates_cycle' => true, 'path' => [$sourceArticleId, ...$path]];
            }

            foreach ($edges[$node] ?? [] as $next) {
                if (isset($visited[$next])) {
                    continue;
                }
                $visited[$next] = true;
                $queue[] = [...$path, $next];
            }
        }

        return ['creates_cycle' => false, 'path' => []];
    }

    /**
     * Costruisce la mappa degli archi reali una sola volta per istanza
     * (memoizzata, UNA sola query): ogni chiamata a detect() nello stesso
     * pannello dei suggerimenti (fino a
     * ArticleLinkSuggestion::MAX_PROPOSED_RESULTS candidati) riusa la
     * stessa mappa invece di interrogare/riparsare di nuovo. Stesso
     * pattern "un solo passaggio sull'intero corpus" già usato da
     * InternalLinkAuditService per lo stesso dominio (nessuna query per
     * articolo, nessun N+1).
     *
     * @return array<int, list<int>>
     */
    private function realEdges(): array
    {
        if ($this->realEdges !== null) {
            return $this->realEdges;
        }

        $articles = Article::query()->get(['id', 'slug', 'body']);
        $idBySlug = $articles->pluck('id', 'slug');

        $this->realEdges = $articles
            ->mapWithKeys(function (Article $article) use ($idBySlug) {
                $targetIds = collect($this->insertionService->linkedArticleSlugsInBody((string) $article->body))
                    ->map(fn (string $slug) => $idBySlug->get($slug))
                    ->filter(fn (?int $id) => $id !== null && $id !== $article->id)
                    ->values()
                    ->all();

                return [$article->id => $targetIds];
            })
            ->filter(fn (array $targetIds) => $targetIds !== [])
            ->all();

        return $this->realEdges;
    }
}
