<?php

/**
 * Kairus — Percorsi: simulazione di riordino (dry-run)
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2026 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Services\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Support\Collection;

/**
 * Missione 18 (secondo batch autonomo KAIRUS, Fase C — Percorsi Advanced
 * Operations): "Create a reusable simulation service that can evaluate a
 * proposed sequence without DB mutation."
 *
 * Legge lo stato reale del Percorso (membership, transition_text, stato di
 * pubblicazione) e valuta un ORDINE PROPOSTO — mai scritto su disco: nessuna
 * chiamata a save()/sync() esiste in questa classe. Riusa
 * ContentClusterPublicSequence::resolveFromOrder() (Missione 18) come unica
 * source of truth per "dove si fermerebbe il prefisso pubblico", esattamente
 * la stessa regola già testata per l'ordine reale — mai una seconda
 * implementazione dello stop-al-primo-gap.
 *
 * Pensata per essere richiamata PRIMA di un salvataggio (dalla Missione 16,
 * o da un futuro endpoint/anteprima), non dopo: risponde alla domanda "cosa
 * succederebbe se salvassi queste posizioni", non "cosa è appena successo".
 */
class PercorsoReorderSimulationService
{
    public function __construct(
        private readonly ContentClusterPublicSequence $publicSequence,
    ) {}

    /**
     * @param  array<int, int>  $proposedPositions  article_id => posizione proposta.
     *                                              Un membro non presente qui mantiene
     *                                              la propria posizione attuale.
     * @return array{
     *     simulated_prefix: Collection<int, Article>,
     *     first_blocker: ?Article,
     *     pillar_reachable: ?bool,
     *     chronology_warnings: list<array{earlier: Article, later: Article}>,
     *     transition_impacts: list<array{article_id: int, article_title: string, position: int}>,
     * }
     */
    public function simulate(ContentCluster $cluster, array $proposedPositions): array
    {
        $cluster->loadMissing(['articles:id,title,slug,status,published_at']);

        $ordered = $cluster->articles
            ->sortBy(function (Article $article) use ($proposedPositions) {
                return $proposedPositions[$article->id] ?? $article->pivot?->position ?? PHP_INT_MAX;
            })
            ->values();

        if ($ordered->isEmpty()) {
            return [
                'simulated_prefix' => collect(),
                'first_blocker' => null,
                'pillar_reachable' => null,
                'chronology_warnings' => [],
                'transition_impacts' => [],
            ];
        }

        $publishedIds = Article::query()
            ->published()
            ->whereIn('id', $ordered->pluck('id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $simulated = $this->publicSequence->resolveFromOrder($ordered, $publishedIds);
        $simulatedPrefix = $simulated['articles'];

        $firstBlocker = $simulated['has_hidden_remainder']
            ? $ordered->get($simulatedPrefix->count())
            : null;

        $pillarReachable = $cluster->pillar_article_id === null
            ? null
            : $simulatedPrefix->contains('id', $cluster->pillar_article_id);

        return [
            'simulated_prefix' => $simulatedPrefix,
            'first_blocker' => $firstBlocker,
            'pillar_reachable' => $pillarReachable,
            'chronology_warnings' => $this->chronologyWarnings($ordered),
            'transition_impacts' => $this->transitionImpacts($ordered),
        ];
    }

    /**
     * Stesso confronto a coppie adiacenti già usato da
     * PercorsoCoverageAuditService::orderHealthForCluster() per l'ordine
     * reale — qui ripetuto (non estratto) perché quel metodo legge sempre
     * $cluster->articles reale e non accetta un ordine proposto: un
     * refactor per condividerlo cambierebbe una classe già testata al di
     * fuori dello scope stretto di questa missione.
     *
     * @param  Collection<int, Article>  $ordered
     * @return list<array{earlier: Article, later: Article}>
     */
    private function chronologyWarnings(Collection $ordered): array
    {
        $warnings = [];

        for ($i = 1; $i < $ordered->count(); $i++) {
            $previous = $ordered->get($i - 1);
            $current = $ordered->get($i);

            if ($previous->published_at !== null && $current->published_at !== null && $current->published_at->lt($previous->published_at)) {
                $warnings[] = ['earlier' => $previous, 'later' => $current];
            }
        }

        return $warnings;
    }

    /**
     * Stessa esenzione della tappa terminale già applicata da
     * PercorsoPublicationReadinessService (TRANSITION_TEXT_GAPS) e dalla
     * tabella/anteprima narrativa admin (Missione 17) — qui contro
     * l'ordine PROPOSTO: un riordino può rendere terminale un membro che
     * oggi non lo è (e viceversa), quindi l'esenzione deve seguire
     * l'ordine simulato, non quello reale.
     *
     * @param  Collection<int, Article>  $ordered
     * @return list<array{article_id: int, article_title: string, position: int}>
     */
    private function transitionImpacts(Collection $ordered): array
    {
        $impacts = [];

        foreach ($ordered as $index => $article) {
            $isLast = $index === $ordered->count() - 1;

            if (! $isLast && ($article->pivot?->transition_text === null || trim((string) $article->pivot->transition_text) === '')) {
                $impacts[] = [
                    'article_id' => (int) $article->id,
                    'article_title' => $article->title,
                    'position' => $index + 1,
                ];
            }
        }

        return $impacts;
    }
}
