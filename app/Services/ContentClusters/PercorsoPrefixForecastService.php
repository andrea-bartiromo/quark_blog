<?php

/**
 * Kairus — Percorsi: previsione di crescita del prefisso pubblico
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2026 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Services\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Support\Carbon;

/**
 * Missione 20 (secondo batch autonomo KAIRUS, Fase C — Percorsi Advanced
 * Operations): "Given scheduled dates, calculate how a path's public prefix
 * is expected to grow over time. Internal/editorial only; do not imply
 * publication guarantees if article status can still change."
 *
 * Riusa ContentClusterPublicSequence::resolve() come unica source of verità
 * per "dove si ferma il prefisso pubblico ORA" (mai una seconda
 * implementazione dello stop-al-primo-gap), poi cammina in avanti dai membri
 * successivi finché restano SCHEDULED con una data che non inverte l'ordine
 * cronologico già raggiunto — esattamente la stessa nozione di "fuori
 * ordine" già usata da PercorsoCoverageAuditService::orderHealthForCluster()
 * (scheduled_out_of_order), qui applicata in avanti come condizione di
 * arresto della previsione anziché come segnalazione retrospettiva.
 *
 * Deliberatamente NON una garanzia di pubblicazione: un membro SCHEDULED può
 * tornare DRAFT prima della sua data, ed è esattamente per questo che il
 * risultato si chiama "forecast" e viene esposto solo lato redazione, mai
 * come promessa sulla pagina pubblica del Percorso.
 */
class PercorsoPrefixForecastService
{
    public function __construct(
        private readonly ContentClusterPublicSequence $publicSequence,
    ) {}

    /**
     * @return array{
     *     current_prefix_length: int,
     *     forecast_steps: list<array{article_id: int, article_title: string, position: int, expected_at: Carbon}>,
     *     blocked_by: ?array{article_id: int, article_title: string, position: int, status: string},
     * }
     */
    public function forecast(ContentCluster $cluster): array
    {
        $cluster->loadMissing(['articles:id,title,slug,status,published_at']);
        $ordered = $cluster->articles->values();

        $sequence = $this->publicSequence->resolve($cluster);
        $currentPrefixLength = $sequence['articles']->count();

        $steps = [];
        $blockedBy = null;
        $previousExpectedAt = null;

        for ($index = $currentPrefixLength; $index < $ordered->count(); $index++) {
            $article = $ordered->get($index);

            if ($article->status !== Article::STATUS_SCHEDULED || $article->published_at === null) {
                $blockedBy = [
                    'article_id' => (int) $article->id,
                    'article_title' => $article->title,
                    'position' => $index + 1,
                    'status' => $article->status,
                ];
                break;
            }

            if ($previousExpectedAt !== null && $article->published_at->lt($previousExpectedAt)) {
                $blockedBy = [
                    'article_id' => (int) $article->id,
                    'article_title' => $article->title,
                    'position' => $index + 1,
                    'status' => 'chronological_inversion',
                ];
                break;
            }

            $steps[] = [
                'article_id' => (int) $article->id,
                'article_title' => $article->title,
                'position' => $index + 1,
                'expected_at' => $article->publishedAtForEditors(),
            ];

            $previousExpectedAt = $article->published_at;
        }

        return [
            'current_prefix_length' => $currentPrefixLength,
            'forecast_steps' => $steps,
            'blocked_by' => $blockedBy,
        ];
    }
}
