<?php

namespace App\Services\InternalLinking;

use App\Models\ArticleLinkSuggestion;

/**
 * Cantiere 82 (programma "100 cantieri Kairus"). Nessun codice esistente
 * verifica se accettare un suggerimento di collegamento interno
 * chiuderebbe un ciclo (A→B→...→A) tra i link GIÀ accettati — confermato
 * da un agente Explore dedicato prima di scrivere questo file: zero
 * occorrenze di "cycle/ciclo/circular" in tutto `app/` e `tests/` per
 * questo dominio, e il resto del sistema (`ArticleLinkSuggestionService`,
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
 */
class ArticleLinkCycleDetector
{
    /** @var array<int, list<int>>|null */
    private ?array $acceptedEdges = null;

    /**
     * Verifica se accettare un arco source→target chiuderebbe un ciclo
     * attraverso i collegamenti GIÀ accettati (mai quelli solo proposti:
     * un ciclo tra suggerimenti ancora in attesa di revisione non è
     * ancora un fatto del contenuto pubblicato).
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

        $edges = $this->acceptedEdges();

        // BFS a partire da $targetArticleId sugli archi già accettati: se
        // si riesce a tornare a $sourceArticleId, accettare il nuovo arco
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
     * Costruisce la mappa degli archi accettati una sola volta per
     * istanza (memoizzata): ogni chiamata a detect() nello stesso
     * pannello dei suggerimenti (fino a
     * ArticleLinkSuggestion::MAX_PROPOSED_RESULTS candidati) riusa la
     * stessa mappa invece di interrogare di nuovo la tabella.
     *
     * @return array<int, list<int>>
     */
    private function acceptedEdges(): array
    {
        if ($this->acceptedEdges !== null) {
            return $this->acceptedEdges;
        }

        $this->acceptedEdges = ArticleLinkSuggestion::query()
            ->where('status', ArticleLinkSuggestion::STATUS_ACCEPTED)
            ->get(['source_article_id', 'target_article_id'])
            ->groupBy('source_article_id')
            ->map(fn ($group) => $group->pluck('target_article_id')->all())
            ->all();

        return $this->acceptedEdges;
    }
}
