<?php

namespace App\Services\EditorialOperations;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Services\ContentClusters\PercorsoCoverageAuditService;
use App\Services\ContentClusters\PercorsoPublicationReadinessService;
use App\Services\ContentHealth\ArticleContentHealthService;
use App\Services\EditorialQuality\SeoMetadataQualityAuditService;
use App\Services\EditorialQuality\SourceImageAttributionHealthService;

/**
 * Editorial Operations Dashboard V1 — recuperato (Mission 09) dal foundation
 * isolato in PR #294 e convergente sui due servizi Percorsi più recenti
 * (Mission 02 — Percorsi Readiness V2, Mission 04 — Editorial Order
 * Health), entrambi assenti alla base di #294.
 *
 * Un solo principio guida ogni sezione: MAI ricalcolare qui una regola già
 * espressa da un servizio esistente — questo aggregatore chiama, raccoglie
 * e riassume, non decide nulla di nuovo. Read-only per costruzione: nessun
 * metodo qui scrive sul database.
 */
class EditorialOperationsDashboardService
{
    public function __construct(
        private readonly ArticleContentHealthService $contentHealth,
        private readonly PercorsoCoverageAuditService $percorsoCoverage,
        private readonly PercorsoPublicationReadinessService $percorsoReadiness,
        private readonly SourceImageAttributionHealthService $attribution,
        private readonly SeoMetadataQualityAuditService $seo,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $articles = Article::query()
            ->whereIn('status', [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED])
            ->with('contentClusters:id')
            ->orderBy('published_at')
            ->orderBy('id')
            ->get();

        $toPublish = $articles
            ->where('status', Article::STATUS_SCHEDULED)
            ->map(fn (Article $article) => [
                'article_id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'published_at' => $article->published_at?->toISOString(),
            ])->values()->all();

        $toFix = $articles->map(function (Article $article): ?array {
            $healthWarnings = $this->contentHealth->evaluate($article)
                ->where('status', ArticleContentHealthService::STATUS_WARNING)
                ->values();
            $attributionWarnings = collect($this->attribution->evaluate($article))
                ->where('status', SourceImageAttributionHealthService::WARNING)
                ->values();

            if ($healthWarnings->isEmpty() && $attributionWarnings->isEmpty()) {
                return null;
            }

            return [
                'article_id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'health_warnings' => $healthWarnings->all(),
                'attribution_warnings' => $attributionWarnings->all(),
            ];
        })->filter()->values()->all();

        $coverage = $this->percorsoCoverage->audit();
        $seo = $this->seo->audit();
        $orderHealth = $this->percorsoCoverage->editorialOrderHealth();
        $orderHealthSummary = $this->orderHealthSummary($orderHealth);
        // Mission 15 — Dashboard Integration: the two summaries below cover
        // genuinely different concerns and must keep listing genuinely
        // different problems separately (see
        // test_a_percorso_with_both_readiness_findings_and_order_health_issues_appears_in_both_sections).
        // But Mission 14 wired one SHARED signal (complete_with_hidden_remainder)
        // into both readiness and order-health, so the same Percorso can now
        // legitimately appear in both sections for the identical root cause —
        // this flag lets the view say so instead of presenting it as two
        // unrelated problems.
        $orderHealthFlaggedClusterIds = collect($orderHealthSummary['clusters_with_issues'])
            ->merge($orderHealthSummary['clusters_with_advisories_only'])
            ->pluck('cluster_id')
            ->all();
        $percorsiReadiness = $this->percorsiReadinessSummary($orderHealthFlaggedClusterIds);

        return [
            'da_pubblicare' => $toPublish,
            'da_sistemare' => $toFix,
            'contenuti_isolati' => $coverage['published_without_path'],
            'seo' => [
                'summary' => $seo['summary'],
                'articles' => $seo['articles'],
            ],
            'percorsi_readiness' => $percorsiReadiness,
            'percorsi_order_health' => $orderHealthSummary,
            'opportunita' => [
                'available' => false,
                'reason' => 'Radar runtime non è ancora su main; nessun dato viene inventato.',
            ],
            'distribuzione' => [
                'available' => false,
                'reason' => 'Social Attribution non è ancora su main; nessun dato viene inventato.',
            ],
        ];
    }

    /**
     * Riusa PercorsoPublicationReadinessService::evaluate() (Mission 02),
     * mai una riga di logica di readiness riscritta qui. Un solo Percorso
     * NON READY/READY WITH WARNINGS per riga — i Percorsi già READY non
     * compaiono (la dashboard mostra solo ciò che richiede attenzione,
     * stesso principio già applicato a "da_sistemare"/"contenuti_isolati").
     *
     * Costo query: O(N) sui Percorsi (un evaluate() per cluster, ciascuno
     * con un numero fisso di query interne — vedi
     * PercorsoPublicationReadinessService::evaluate(), che risolve la
     * sequenza pubblica reale via ContentClusterPublicSequence::resolve()
     * per ciascun cluster). Deliberatamente non "aggirato" con una
     * reimplementazione locale della stessa regola solo per renderlo O(1):
     * i Percorsi sono un catalogo editoriale curato a mano (unità, non
     * migliaia, a differenza degli Articoli), quindi la crescita lineare
     * resta trascurabile in pratica — provato dal test di query budget.
     *
     * @param  list<int>  $orderHealthFlaggedClusterIds
     * @return array<int, array<string, mixed>>
     */
    private function percorsiReadinessSummary(array $orderHealthFlaggedClusterIds): array
    {
        return ContentCluster::query()
            ->ordered()
            ->get()
            ->map(function (ContentCluster $cluster) use ($orderHealthFlaggedClusterIds) {
                $result = $this->percorsoReadiness->evaluate($cluster);

                if ($result['status'] === 'READY') {
                    return null;
                }

                return [
                    'cluster_id' => $cluster->id,
                    'name' => $cluster->name,
                    'slug' => $cluster->slug,
                    'status' => $result['status'],
                    'finding_count' => $result['findings']->count(),
                    'error_count' => $result['findings']->where('severity', 'ERROR')->count(),
                    'warning_count' => $result['findings']->where('severity', 'WARNING')->count(),
                    // Mission 15: which ERROR/WARNING codes are actually
                    // driving this row — INFO codes (e.g. SCHEDULING_NOT_AVAILABLE)
                    // stay out of the visible list, same as they already stay
                    // out of error_count/warning_count above.
                    'codes' => $result['findings']
                        ->whereIn('severity', ['ERROR', 'WARNING'])
                        ->pluck('code')
                        ->values()
                        ->all(),
                    'also_in_order_health' => in_array($cluster->id, $orderHealthFlaggedClusterIds, true),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Riassume editorialOrderHealth() (Mission 04) in conteggi utili per
     * una card — mai un ricalcolo delle categorie, solo un conteggio dei
     * risultati già classificati dal servizio.
     *
     * @param  array<string, mixed>  $orderHealth
     * @return array<string, mixed>
     */
    private function orderHealthSummary(array $orderHealth): array
    {
        $structuralErrorCount = collect($orderHealth['structural_error'])->sum(fn (array $rows) => count($rows));
        $publicationWarningCount = collect($orderHealth['publication_warning'])->sum(fn (array $rows) => count($rows));
        $editorialAdvisoryCount = collect($orderHealth['editorial_advisory'])->sum(fn (array $rows) => count($rows));

        $blockingIssue = fn (array $row) => $row['missing_position'] !== []
            || $row['non_positive_position'] !== []
            || $row['duplicate_position'] !== []
            || $row['published_beyond_gap'] !== []
            || $row['pillar_outside_reachable_prefix']
            || $row['complete_with_hidden_remainder'];

        $advisoryOnly = fn (array $row) => $row['chronological_inversions'] !== []
            || $row['scheduled_out_of_order'] !== []
            || $row['dangling_transition'] !== null;

        return [
            'structural_error_count' => $structuralErrorCount,
            'publication_warning_count' => $publicationWarningCount,
            'editorial_advisory_count' => $editorialAdvisoryCount,
            'clusters_with_issues' => collect($orderHealth['clusters'])
                ->filter($blockingIssue)
                ->map(fn (array $row) => ['cluster_id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug']])
                ->values()
                ->all(),
            // Percorsi la cui unica segnalazione è un editorial_advisory:
            // mai bloccante per design (vedi editorialOrderHealth()), quindi
            // elencati separatamente e mai fusi in clusters_with_issues —
            // un editor non deve leggerli come "da correggere".
            'clusters_with_advisories_only' => collect($orderHealth['clusters'])
                ->filter(fn (array $row) => ! $blockingIssue($row) && $advisoryOnly($row))
                ->map(fn (array $row) => ['cluster_id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug']])
                ->values()
                ->all(),
        ];
    }
}
